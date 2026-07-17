<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\JournalAdmin;
use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Enum\TypeActionJournal;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Test d'integration de la commande de purge d'audit (US-5.3).
 * Verifie que seules les entrees anterieures au seuil sont supprimees, dans les deux tables d'audit,
 * et que le mode simulation ne supprime rien. Transaction annulee en tearDown (aucune pollution).
 */
final class PurgeAuditCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $connexion;
    private CommandTester $commandTester;
    private int $utilisateurId;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->connexion = $this->em->getConnection();
        $this->connexion->beginTransaction();

        // historique_utilisateur porte une FK vers utilisateur : il faut un compte reel pour l'INSERT natif.
        $utilisateur = (new Utilisateur())
            ->setEmail('purge-audit.' . uniqid() . '@creapret.local')
            ->setNom('Testeur')
            ->setPrenom('Purge')
            ->setRole(Role::GESTIONNAIRE)
            ->setEstActif(true);
        $utilisateur->setMotDePasseHash('hash-fictif-test');
        $this->em->persist($utilisateur);
        $this->em->flush();
        $this->utilisateurId = (int) $utilisateur->getId();

        $application = new Application($kernel);
        $this->commandTester = new CommandTester($application->find('app:audit:purger'));
    }

    protected function tearDown(): void
    {
        if ($this->connexion->isTransactionActive()) {
            $this->connexion->rollBack();
        }
        parent::tearDown();
    }

    public function test_la_purge_supprime_seulement_les_entrees_anciennes(): void
    {
        $this->creerEntreeJournal(400); // ancienne (> 365 j) -> purgee
        $this->creerEntreeJournal(10);  // recente -> conservee
        $this->creerEntreeHistorique(400);
        $this->creerEntreeHistorique(10);

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertSame(1, $this->compterJournal(), 'Seule l entree recente de journal doit rester.');
        self::assertSame(1, $this->compterHistorique(), 'Seule l entree recente d historique doit rester.');
    }

    public function test_dry_run_ne_supprime_rien(): void
    {
        $this->creerEntreeJournal(400);
        $this->creerEntreeHistorique(400);

        $this->commandTester->execute(['--dry-run' => true]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertSame(1, $this->compterJournal(), 'Le dry-run ne doit rien supprimer.');
        self::assertSame(1, $this->compterHistorique(), 'Le dry-run ne doit rien supprimer.');
    }

    public function test_jours_invalide_retourne_invalid(): void
    {
        $code = $this->commandTester->execute(['--jours' => '0']);

        self::assertSame(2, $code); // Command::INVALID
    }

    private function creerEntreeJournal(int $joursDansLePasse): void
    {
        $entree = new JournalAdmin(TypeActionJournal::PRET_VALIDATION, $this->utilisateurId, 'Testeur Purge');
        $date = (new \DateTimeImmutable())->modify(sprintf('-%d days', $joursDansLePasse));
        $reflexion = new \ReflectionProperty(JournalAdmin::class, 'dateAction');
        $reflexion->setValue($entree, $date);
        $this->em->persist($entree);
        $this->em->flush();
    }

    private function creerEntreeHistorique(int $joursDansLePasse): void
    {
        $date = (new \DateTimeImmutable())->modify(sprintf('-%d days', $joursDansLePasse))->format('Y-m-d H:i:s');
        $this->connexion->executeStatement(
            'INSERT INTO historique_utilisateur (utilisateur_id, champ_modifie, ancienne_valeur, nouvelle_valeur, date_modification) VALUES (:uid, :champ, :ancien, :nouveau, :date)',
            ['uid' => $this->utilisateurId, 'champ' => 'role', 'ancien' => 'emprunteur', 'nouveau' => 'gestionnaire', 'date' => $date],
        );
    }

    private function compterJournal(): int
    {
        return (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM journal_admin WHERE acteur_id = :uid', ['uid' => $this->utilisateurId]);
    }

    private function compterHistorique(): int
    {
        return (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM historique_utilisateur WHERE utilisateur_id = :uid', ['uid' => $this->utilisateurId]);
    }
}
