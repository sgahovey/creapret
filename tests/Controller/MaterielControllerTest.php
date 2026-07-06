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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MaterielControllerTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-mat';

    private function gestionnaire(EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('gest.mat.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('Gest')
            ->setPrenom('Mat')
            ->setRole(Role::GESTIONNAIRE)
            ->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'Motdepasse1!'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function categorie(EntityManagerInterface $em): Categorie
    {
        $c = (new Categorie())->setNom(self::MARQUEUR . '-cat-' . uniqid());
        $em->persist($c);
        $em->flush();

        return $c;
    }

    private function purger(EntityManagerInterface $em): void
    {
        // Ordre impose par les FK : exemplaires -> materiels -> categories -> utilisateurs.
        $em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'gest.mat.%')->execute();
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purger($em);
        parent::tearDown();
    }

    public function test_liste_accessible_au_gestionnaire(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/materiel');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Materiels');
    }

    public function test_creation_valide_avec_categorie(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $cat = $this->categorie($em);
        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/materiel/nouveau');

        $nom = self::MARQUEUR . '-cree';
        $client->submitForm('Enregistrer', [
            'materiel[nom]'       => $nom,
            'materiel[categorie]' => (string) $cat->getId(),
            'materiel[marque]'    => 'Dell',
        ]);

        self::assertResponseRedirects('/gestion/materiel');
        self::assertNotNull($em->getRepository(Materiel::class)->findOneBy(['nom' => $nom]));
    }

    public function test_creation_invalide_nom_vide(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $cat = $this->categorie($em);
        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/materiel/nouveau');

        $client->submitForm('Enregistrer', [
            'materiel[nom]'       => '',
            'materiel[categorie]' => (string) $cat->getId(),
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_edition(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $cat = $this->categorie($em);
        $m = (new Materiel())->setNom(self::MARQUEUR . '-avant')->setCategorie($cat);
        $em->persist($m);
        $em->flush();
        $id = $m->getId();

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/materiel/' . $id . '/modifier');
        $client->submitForm('Enregistrer', [
            'materiel[nom]'       => self::MARQUEUR . '-apres',
            'materiel[categorie]' => (string) $cat->getId(),
        ]);

        self::assertResponseRedirects('/gestion/materiel');
        $em->clear();
        $modifie = $em->getRepository(Materiel::class)->find($id);
        self::assertInstanceOf(Materiel::class, $modifie);
        self::assertSame(self::MARQUEUR . '-apres', $modifie->getNom());
    }

    public function test_suppression_reussie_sans_exemplaire(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $cat = $this->categorie($em);
        $m = (new Materiel())->setNom(self::MARQUEUR . '-a-supprimer')->setCategorie($cat);
        $em->persist($m);
        $em->flush();
        $id = $m->getId();

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/materiel');
        $client->submitForm('Supprimer', []);

        self::assertResponseRedirects('/gestion/materiel');
        $em->clear();
        self::assertNull($em->getRepository(Materiel::class)->find($id));
    }

    public function test_suppression_bloquee_si_exemplaire_rattache(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $cat = $this->categorie($em);
        $m = (new Materiel())->setNom(self::MARQUEUR . '-avec-ex')->setCategorie($cat);
        $em->persist($m);
        $e = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-inv-' . uniqid())
            ->setEtat(EtatExemplaire::DISPONIBLE)
            ->setMateriel($m);
        $em->persist($e);
        $em->flush();
        $id = $m->getId();

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/materiel');
        $client->submitForm('Supprimer', []);

        // FK RESTRICT (DC-10) : refus, le materiel existe toujours.
        self::assertResponseRedirects('/gestion/materiel');
        $em->clear();
        self::assertNotNull($em->getRepository(Materiel::class)->find($id));
    }
}
