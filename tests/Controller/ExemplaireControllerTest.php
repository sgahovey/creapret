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

final class ExemplaireControllerTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-ex';

    private function gestionnaire(EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('gest.ex.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('Gest')
            ->setPrenom('Ex')
            ->setRole(Role::GESTIONNAIRE)
            ->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'Motdepasse1!'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function materiel(EntityManagerInterface $em): Materiel
    {
        $c = (new Categorie())->setNom(self::MARQUEUR . '-cat-' . uniqid());
        $em->persist($c);
        $m = (new Materiel())->setNom(self::MARQUEUR . '-mat-' . uniqid())->setCategorie($c);
        $em->persist($m);
        $em->flush();

        return $m;
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
            ->setParameter('p', 'gest.ex.%')->execute();
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
        $client->request('GET', '/gestion/exemplaire');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Exemplaires');
    }

    public function test_creation_valide(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $mat = $this->materiel($em);
        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/exemplaire/nouveau');

        $numero = self::MARQUEUR . '-' . uniqid();
        $client->submitForm('Enregistrer', [
            'exemplaire[numeroInventaire]' => $numero,
            'exemplaire[materiel]'         => (string) $mat->getId(),
            'exemplaire[etat]'             => EtatExemplaire::DISPONIBLE->value,
        ]);

        self::assertResponseRedirects('/gestion/exemplaire');
        self::assertNotNull($em->getRepository(Exemplaire::class)->findOneBy(['numeroInventaire' => $numero]));
    }

    public function test_creation_numero_duplique_est_rejetee(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $mat = $this->materiel($em);
        $numero = self::MARQUEUR . '-dup-' . uniqid();
        $existant = (new Exemplaire())
            ->setNumeroInventaire($numero)
            ->setEtat(EtatExemplaire::DISPONIBLE)
            ->setMateriel($mat);
        $em->persist($existant);
        $em->flush();

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/exemplaire/nouveau');
        $client->submitForm('Enregistrer', [
            'exemplaire[numeroInventaire]' => $numero,
            'exemplaire[materiel]'         => (string) $mat->getId(),
            'exemplaire[etat]'             => EtatExemplaire::DISPONIBLE->value,
        ]);

        // UniqueEntity : doublon rejete au formulaire (pas d'exception SQL).
        self::assertResponseStatusCodeSame(422);
    }

    public function test_l_etat_prete_est_absent_du_formulaire(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $client->loginUser($this->gestionnaire($em, $hasher));
        $crawler = $client->request('GET', '/gestion/exemplaire/nouveau');

        $options = $crawler->filter('select[name="exemplaire[etat]"] option')->each(
            static fn ($node) => $node->attr('value'),
        );

        // PRETE ne doit pas etre proposable manuellement (resulte d'un pret, iteration 3).
        self::assertNotContains(EtatExemplaire::PRETE->value, $options);
        self::assertContains(EtatExemplaire::DISPONIBLE->value, $options);
        self::assertContains(EtatExemplaire::EN_MAINTENANCE->value, $options);
    }

    public function test_edition_de_l_etat(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $mat = $this->materiel($em);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-edit-' . uniqid())
            ->setEtat(EtatExemplaire::DISPONIBLE)
            ->setMateriel($mat);
        $em->persist($ex);
        $em->flush();
        $id = $ex->getId();

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/exemplaire/' . $id . '/modifier');
        $client->submitForm('Enregistrer', [
            'exemplaire[etat]' => EtatExemplaire::EN_MAINTENANCE->value,
        ]);

        self::assertResponseRedirects('/gestion/exemplaire');
        $em->clear();
        $modifie = $em->getRepository(Exemplaire::class)->find($id);
        self::assertInstanceOf(Exemplaire::class, $modifie);
        self::assertSame(EtatExemplaire::EN_MAINTENANCE, $modifie->getEtat());
    }

    public function test_suppression(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $mat = $this->materiel($em);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-del-' . uniqid())
            ->setEtat(EtatExemplaire::DISPONIBLE)
            ->setMateriel($mat);
        $em->persist($ex);
        $em->flush();
        $id = $ex->getId();

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/exemplaire');
        $client->submitForm('Supprimer', []);

        self::assertResponseRedirects('/gestion/exemplaire');
        $em->clear();
        self::assertNull($em->getRepository(Exemplaire::class)->find($id));
    }
}
