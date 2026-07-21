<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Repository\PretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class GestionPretControllerTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-gestion-pret';

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }

    private function purger(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\Entity\Pret p WHERE EXISTS (SELECT e2.id FROM App\Entity\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'gestionpret.%')->execute();
        // Le journal d'administration est append-only et SANS cle etrangere : aucune suppression
        // en cascade ne l'atteint, et ses libelles figes (acteur/cible) ne portent pas le marqueur
        // de ce test. Une purge par marqueur ne peut donc pas etre etanche ici -> remise a zero
        // DETERMINISTE : en base de TEST, cette table n'a pas vocation a survivre a un test.
        $em->createQuery('DELETE FROM App\\Entity\\JournalAdmin j')->execute();
    }

    private function utilisateur(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, Role $role, string $suffixe): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('gestionpret.' . $suffixe . '.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('T')->setPrenom($suffixe)
            ->setRole($role)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'motdepasse'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function demande(EntityManagerInterface $em, Utilisateur $emprunteur, string $debut, string $fin): Pret
    {
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $em->persist($ex);
        $pret = (new Pret())->setExemplaire($ex)->setEmprunteur($emprunteur)
            ->setDateDebut(new \DateTimeImmutable($debut))->setDateFin(new \DateTimeImmutable($fin));
        $em->persist($pret);
        $em->flush();

        return $pret;
    }

    public function test_un_emprunteur_n_accede_pas_a_la_gestion(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $client->loginUser($this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp'));
        $client->request('GET', '/gestion/prets');
        self::assertResponseStatusCodeSame(403);
    }

    public function test_le_gestionnaire_voit_les_demandes_en_attente(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $this->demande($em, $emprunteur, '2026-09-10 00:00:00', '2026-09-15 00:00:00');

        $client->loginUser($this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest'));
        $client->request('GET', '/gestion/prets');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Demandes de prêt en attente');
    }

    public function test_valider_une_demande_la_passe_en_valide(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $pret = $this->demande($em, $emprunteur, '2026-09-10 00:00:00', '2026-09-15 00:00:00');
        $idPret = $pret->getId();

        $client->loginUser($this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest'));
        $client->request('GET', '/gestion/prets');
        $client->submitForm('Valider');

        self::assertResponseRedirects('/gestion/prets');
        $em->clear();
        $relu = $client->getContainer()->get(PretRepository::class)->find($idPret);
        self::assertNotNull($relu);
        self::assertSame(StatutPret::VALIDE, $relu->getStatut());
    }

    public function test_refuser_une_demande_avec_motif(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $pret = $this->demande($em, $emprunteur, '2026-09-10 00:00:00', '2026-09-15 00:00:00');
        $idPret = $pret->getId();

        $client->loginUser($this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest'));
        $client->request('GET', '/gestion/prets/' . $idPret . '/refuser');
        self::assertResponseIsSuccessful();

        $client->submitForm('Confirmer le refus', [
            'refus_pret[motif]' => 'Materiel reserve pour une formation.',
        ]);
        self::assertResponseRedirects('/gestion/prets');

        $em->clear();
        $relu = $client->getContainer()->get(PretRepository::class)->find($idPret);
        self::assertNotNull($relu);
        self::assertSame(StatutPret::REFUSE, $relu->getStatut());
        self::assertSame('Materiel reserve pour une formation.', $relu->getMotifRefus());
    }

    protected function tearDown(): void
    {
        // Reutilise le kernel deja boote par le test (createClient ne doit etre appele qu'une fois).
        $this->purger(static::getContainer()->get(EntityManagerInterface::class));
        parent::tearDown();
    }

    public function test_validation_avec_jeton_invalide_est_rejetee(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $pret = $this->demande($em, $emprunteur, '2026-09-10 00:00:00', '2026-09-15 00:00:00');
        $idPret = $pret->getId();

        $client->loginUser($this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest'));
        // Jeton CSRF invalide : la validation est refusee, le pret reste en attente.
        $client->request('POST', '/gestion/prets/' . $idPret . '/valider', ['_token' => 'faux']);

        self::assertResponseRedirects('/gestion/prets');
        $em->clear();
        $relu = $client->getContainer()->get(PretRepository::class)->find($idPret);
        self::assertNotNull($relu);
        self::assertSame(StatutPret::DEMANDE, $relu->getStatut());
    }

    public function test_retour_avec_jeton_invalide_est_rejete(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $pret = $this->demande($em, $emprunteur, '2026-09-10 00:00:00', '2026-09-15 00:00:00');
        $idPret = $pret->getId();

        $client->loginUser($this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest'));
        // Valider d'abord (jeton valide via le formulaire) pour passer le pret en cours.
        $client->request('GET', '/gestion/prets');
        $client->submitForm('Valider');
        self::assertResponseRedirects('/gestion/prets');

        // Puis tenter le retour avec un jeton CSRF invalide : refuse, le pret reste VALIDE.
        $client->request('POST', '/gestion/prets/' . $idPret . '/retour', ['_token' => 'faux']);

        self::assertResponseRedirects('/gestion/prets/retours');
        $em->clear();
        $relu = $client->getContainer()->get(PretRepository::class)->find($idPret);
        self::assertNotNull($relu);
        self::assertSame(StatutPret::VALIDE, $relu->getStatut());
    }
}
