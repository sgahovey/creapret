<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test d'integration du trigger d'audit AFTER UPDATE (US-5.2).
 *
 * Verifie qu'une modification des champs sensibles d'un compte (role, activation) genere
 * automatiquement, cote base, une ligne dans historique_utilisateur, et que la procedure stockee
 * consulter_historique_utilisateur restitue cette trace. Le test s'execute dans une transaction
 * annulee en tearDown : le trigger insere dans la meme transaction, on lit donc avant le rollback,
 * sans polluer la base.
 */
final class HistoriqueUtilisateurTriggerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $connexion;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $this->em->getConnection();
        $this->connexion->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }
        parent::tearDown();
    }

    public function test_le_trigger_trace_le_changement_de_role(): void
    {
        $utilisateur = $this->creerUtilisateur();

        $utilisateur->setRole(Role::GESTIONNAIRE);
        $this->em->flush(); // UPDATE reel -> declenche le trigger

        $ligne = $this->connexion->executeQuery(
            'SELECT ancienne_valeur, nouvelle_valeur FROM historique_utilisateur WHERE utilisateur_id = ? AND champ_modifie = ?',
            [$utilisateur->getId(), 'role'],
        )->fetchAssociative();

        self::assertIsArray($ligne, 'Une ligne d historique doit exister pour le champ role.');
        self::assertSame('emprunteur', $ligne['ancienne_valeur']);
        self::assertSame('gestionnaire', $ligne['nouvelle_valeur']);
    }

    public function test_le_trigger_trace_la_desactivation(): void
    {
        $utilisateur = $this->creerUtilisateur();

        $utilisateur->setEstActif(false);
        $this->em->flush();

        $ligne = $this->connexion->executeQuery(
            'SELECT ancienne_valeur, nouvelle_valeur FROM historique_utilisateur WHERE utilisateur_id = ? AND champ_modifie = ?',
            [$utilisateur->getId(), 'est_actif'],
        )->fetchAssociative();

        self::assertIsArray($ligne, 'Une ligne d historique doit exister pour le champ est_actif.');
        self::assertSame('1', $ligne['ancienne_valeur']);
        self::assertSame('0', $ligne['nouvelle_valeur']);
    }

    public function test_la_procedure_restitue_l_historique(): void
    {
        $utilisateur = $this->creerUtilisateur();

        $utilisateur->setRole(Role::GESTIONNAIRE);
        $this->em->flush();
        $utilisateur->setEstActif(false);
        $this->em->flush();

        // La procedure renvoie plusieurs result sets (CALL) : liberer le curseur apres lecture,
        // sinon la requete suivante echoue (unbuffered queries active).
        $resultat = $this->connexion->executeQuery(
            'CALL consulter_historique_utilisateur(?)',
            [$utilisateur->getId()],
        );
        $lignes = $resultat->fetchAllAssociative();
        $resultat->free();

        self::assertCount(2, $lignes, 'La procedure doit restituer les deux modifications.');
        $champs = array_column($lignes, 'champ_modifie');
        self::assertContains('role', $champs);
        self::assertContains('est_actif', $champs);
    }

    private function creerUtilisateur(): Utilisateur
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $utilisateur = (new Utilisateur())
            ->setEmail('trigtest.' . uniqid() . '@creapret.local')
            ->setNom('Trigger')
            ->setPrenom('Test')
            ->setRole(Role::EMPRUNTEUR)
            ->setEstActif(true);
        $utilisateur->setMotDePasseHash($hasher->hashPassword($utilisateur, 'motdepasse'));

        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }
}
