<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CatalogueControllerTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-cata';

    private function emprunteur(EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('empr.cata.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('Empr')->setPrenom('Cata')
            ->setRole(Role::EMPRUNTEUR)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'Motdepasse1!'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    /** @return array{Categorie, Materiel} */
    private function materielAvecExemplaires(EntityManagerInterface $em, int $nbDisponibles, int $nbMaintenance = 0): array
    {
        $c = (new Categorie())->setNom(self::MARQUEUR . '-cat-' . uniqid());
        $em->persist($c);
        $m = (new Materiel())->setNom(self::MARQUEUR . '-mat-' . uniqid())->setCategorie($c);
        $em->persist($m);

        for ($i = 0; $i < $nbDisponibles; ++$i) {
            $em->persist((new Exemplaire())
                ->setNumeroInventaire(self::MARQUEUR . '-d-' . uniqid())
                ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($m));
        }
        for ($i = 0; $i < $nbMaintenance; ++$i) {
            $em->persist((new Exemplaire())
                ->setNumeroInventaire(self::MARQUEUR . '-m-' . uniqid())
                ->setEtat(EtatExemplaire::EN_MAINTENANCE)->setMateriel($m));
        }
        $em->flush();

        return [$c, $m];
    }

    private function contenu(KernelBrowser $client): string
    {
        return (string) $client->getResponse()->getContent();
    }

    private function purger(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'empr.cata.%')->execute();
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purger($em);
        parent::tearDown();
    }

    public function test_anonyme_est_redirige_vers_la_connexion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/catalogue');

        self::assertResponseRedirects('/connexion');
    }

    public function test_emprunteur_accede_au_catalogue_mais_pas_a_la_gestion(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $client->loginUser($this->emprunteur($em, $hasher));

        // Separation des zones : le catalogue lui est ouvert...
        $client->request('GET', '/catalogue');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Catalogue');

        // ...mais la gestion lui est interdite (403).
        $client->request('GET', '/gestion');
        self::assertResponseStatusCodeSame(403);
    }

    public function test_la_disponibilite_est_correcte_et_le_materiel_a_zero_reste_affiche(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Materiel A : 2 disponibles + 1 en maintenance ; Materiel B : 0 disponible.
        [, $matA] = $this->materielAvecExemplaires($em, 2, 1);
        [, $matB] = $this->materielAvecExemplaires($em, 0, 1);

        $client->loginUser($this->emprunteur($em, $hasher));
        $client->request('GET', '/catalogue');

        self::assertResponseIsSuccessful();
        $contenu = $this->contenu($client);
        // Les deux materiels sont presents (WITH du LEFT JOIN : le 0-dispo reste affiche).
        self::assertStringContainsString($matA->getNom(), $contenu);
        self::assertStringContainsString($matB->getNom(), $contenu);
        // Le materiel A affiche bien "2 disponibles".
        self::assertStringContainsString('2 disponible', $contenu);
    }

    public function test_filtre_par_categorie(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        [$catA, $matA] = $this->materielAvecExemplaires($em, 1);
        [, $matB] = $this->materielAvecExemplaires($em, 1);

        $client->loginUser($this->emprunteur($em, $hasher));
        $client->request('GET', '/catalogue?categorie=' . $catA->getId());

        self::assertResponseIsSuccessful();
        $contenu = $this->contenu($client);
        self::assertStringContainsString($matA->getNom(), $contenu);
        self::assertStringNotContainsString($matB->getNom(), $contenu);
    }

    public function test_fiche_materiel_accessible(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        [, $mat] = $this->materielAvecExemplaires($em, 3);

        $client->loginUser($this->emprunteur($em, $hasher));
        $client->request('GET', '/catalogue/' . $mat->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $mat->getNom());
        self::assertStringContainsString('3 exemplaire', $this->contenu($client));
    }

    public function test_fiche_materiel_inexistant_donne_404(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $client->loginUser($this->emprunteur($em, $hasher));
        $client->request('GET', '/catalogue/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_disponibilite_sur_periode_et_entete_no_store(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Materiel avec 2 exemplaires disponibles.
        [, $mat] = $this->materielAvecExemplaires($em, 2);

        $client->loginUser($this->emprunteur($em, $hasher));
        $client->request('GET', '/catalogue/' . $mat->getId() . '?debut=2026-09-10&fin=2026-09-15');

        self::assertResponseIsSuccessful();
        // En-tete no-store : la disponibilite ne doit jamais etre mise en cache.
        // Symfony normalise Cache-Control (ajoute private/must-revalidate/max-age=0) :
        // on verifie la presence de la directive no-store, pas une egalite exacte.
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('2 exemplaire', $this->contenu($client));
    }

    public function test_periode_invalide_affiche_un_message(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        [, $mat] = $this->materielAvecExemplaires($em, 1);

        $client->loginUser($this->emprunteur($em, $hasher));
        // fin <= debut : periode invalide.
        $client->request('GET', '/catalogue/' . $mat->getId() . '?debut=2026-09-15&fin=2026-09-10');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('postérieure', $this->contenu($client));
    }
}
