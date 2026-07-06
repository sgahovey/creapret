<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Materiel;
use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CategorieControllerTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-cat';

    private function gestionnaire(EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Utilisateur
    {
        $email = 'gest.cat.' . uniqid() . '@cnam-reunion.fr';
        $u = (new Utilisateur())
            ->setEmail($email)
            ->setNom('Gest')
            ->setPrenom('Cat')
            ->setRole(Role::GESTIONNAIRE)
            ->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'Motdepasse1!'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function purger(EntityManagerInterface $em): void
    {
        // Ordre impose par le FK : materiels d'abord, puis categories, puis utilisateurs de test.
        $em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'gest.cat.%')->execute();
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
        $client->request('GET', '/gestion/categorie');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Categories');
    }

    public function test_creation_valide(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/categorie/nouvelle');

        $nom = self::MARQUEUR . '-creee';
        $client->submitForm('Enregistrer', [
            'categorie[nom]'         => $nom,
            'categorie[description]' => 'Description de test',
        ]);

        self::assertResponseRedirects('/gestion/categorie');
        $trouvee = $em->getRepository(Categorie::class)->findOneBy(['nom' => $nom]);
        self::assertNotNull($trouvee);
    }

    public function test_creation_invalide_nom_vide(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/categorie/nouvelle');

        $client->submitForm('Enregistrer', [
            'categorie[nom]' => '',
        ]);

        // Formulaire invalide : pas de redirection (re-render).
        self::assertResponseStatusCodeSame(422);
    }

    public function test_edition(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $c = (new Categorie())->setNom(self::MARQUEUR . '-avant');
        $em->persist($c);
        $em->flush();
        $id = $c->getId();

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/categorie/' . $id . '/modifier');
        $client->submitForm('Enregistrer', [
            'categorie[nom]' => self::MARQUEUR . '-apres',
        ]);

        self::assertResponseRedirects('/gestion/categorie');
        $em->clear();
        $modifiee = $em->getRepository(Categorie::class)->find($id);
        self::assertInstanceOf(Categorie::class, $modifiee);
        self::assertSame(self::MARQUEUR . '-apres', $modifiee->getNom());
    }

    public function test_suppression_reussie_sans_materiel(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $c = (new Categorie())->setNom(self::MARQUEUR . '-a-supprimer');
        $em->persist($c);
        $em->flush();
        $id = $c->getId();

        $client->loginUser($this->gestionnaire($em, $hasher));
        // Passer par la page liste pour recuperer un token CSRF valide via le formulaire.
        $client->request('GET', '/gestion/categorie');
        $client->submitForm('Supprimer', []);

        self::assertResponseRedirects('/gestion/categorie');
        $em->clear();
        self::assertNull($em->getRepository(Categorie::class)->find($id));
    }

    public function test_suppression_bloquee_si_materiel_rattache(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $c = (new Categorie())->setNom(self::MARQUEUR . '-avec-materiel');
        $em->persist($c);
        $m = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($c);
        $em->persist($m);
        $em->flush();
        $id = $c->getId();

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/categorie');
        $client->submitForm('Supprimer', []);

        // FK RESTRICT (DC-10) : refus, la categorie existe toujours.
        self::assertResponseRedirects('/gestion/categorie');
        $em->clear();
        self::assertNotNull($em->getRepository(Categorie::class)->find($id));
    }
}
