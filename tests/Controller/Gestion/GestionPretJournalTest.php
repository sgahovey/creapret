<?php

declare(strict_types=1);

namespace App\Tests\Controller\Gestion;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\JournalAdmin;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\TypeActionJournal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifie que les decisions du gestionnaire sur les prets laissent une trace figee dans le journal
 * d'administration (US-5.3) : validation et refus. Le marqueur 'zzjournal' dans le nom des comptes de
 * test permet une purge ciblee du journal (table sans cle etrangere, non nettoyee par cascade).
 */
final class GestionPretJournalTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-journal';

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }

    private function utilisateur(KernelBrowser $client, Role $role, string $prenom): Utilisateur
    {
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $u = (new Utilisateur())
            ->setEmail('journal.' . uniqid() . '@creapret.local')
            ->setNom('zzjournal')->setPrenom($prenom)
            ->setRole($role)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'motdepasse'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function demande(KernelBrowser $client, Utilisateur $emprunteur): Pret
    {
        $em = $this->em($client);
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $em->persist($mat);
        $ex = (new Exemplaire())->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $em->persist($ex);
        $pret = (new Pret())->setExemplaire($ex)->setEmprunteur($emprunteur)
            ->setDateDebut(new \DateTimeImmutable('2026-09-10'))->setDateFin(new \DateTimeImmutable('2026-09-15'));
        $em->persist($pret);
        $em->flush();

        return $pret;
    }

    public function test_valider_journalise_la_decision(): void
    {
        $client = static::createClient();
        $emprunteur = $this->utilisateur($client, Role::EMPRUNTEUR, 'emp');
        $gestionnaire = $this->utilisateur($client, Role::GESTIONNAIRE, 'gest');
        $this->demande($client, $emprunteur);

        $client->loginUser($gestionnaire);
        $client->request('GET', '/gestion/prets');
        $client->submitForm('Valider');
        self::assertResponseRedirects('/gestion/prets');

        $em = $this->em($client);
        $em->clear();
        $trace = $em->getRepository(JournalAdmin::class)->findOneBy([
            'typeAction' => TypeActionJournal::PRET_VALIDATION,
            'acteurId'   => $gestionnaire->getId(),
            'cibleId'    => $emprunteur->getId(),
        ]);

        self::assertNotNull($trace, 'La validation doit etre journalisee.');
        self::assertSame('gest zzjournal', $trace->getActeurLibelle());
        self::assertSame('emp zzjournal', $trace->getCibleLibelle());
    }

    public function test_refuser_journalise_la_decision_avec_le_motif(): void
    {
        $client = static::createClient();
        $emprunteur = $this->utilisateur($client, Role::EMPRUNTEUR, 'emp');
        $gestionnaire = $this->utilisateur($client, Role::GESTIONNAIRE, 'gest');
        $pret = $this->demande($client, $emprunteur);
        $motif = self::MARQUEUR . ' ' . uniqid();

        $client->loginUser($gestionnaire);
        $client->request('GET', '/gestion/prets/' . $pret->getId() . '/refuser');
        $client->submitForm('Confirmer le refus', ['refus_pret[motif]' => $motif]);
        self::assertResponseRedirects('/gestion/prets');

        $em = $this->em($client);
        $em->clear();
        $trace = $em->getRepository(JournalAdmin::class)->findOneBy(['details' => $motif]);

        self::assertNotNull($trace, 'Le refus doit etre journalise.');
        self::assertSame(TypeActionJournal::PRET_REFUS, $trace->getTypeAction());
        self::assertSame($gestionnaire->getId(), $trace->getActeurId());
        self::assertSame($emprunteur->getId(), $trace->getCibleId());
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\\Entity\\JournalAdmin j WHERE j.acteurLibelle LIKE :m OR j.cibleLibelle LIKE :m')
            ->setParameter('m', '%zzjournal%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Pret p WHERE EXISTS (SELECT e2.id FROM App\\Entity\\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Exemplaire e WHERE e.numeroInventaire LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Materiel m WHERE m.nom LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Categorie c WHERE c.nom LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Utilisateur u WHERE u.email LIKE :p')->setParameter('p', 'journal.%')->execute();
        parent::tearDown();
    }
}
