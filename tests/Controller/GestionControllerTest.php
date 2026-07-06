<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class GestionControllerTest extends WebTestCase
{
    private function creerUtilisateur(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        string $email,
        Role $role,
    ): Utilisateur {
        $u = (new Utilisateur())
            ->setEmail($email)
            ->setNom('Test')
            ->setPrenom('Gestion')
            ->setRole($role)
            ->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'Motdepasse1!'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function purger(EntityManagerInterface $em, string $email): void
    {
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email = :e')
            ->setParameter('e', $email)
            ->execute();
    }

    public function test_un_anonyme_est_redirige_vers_la_connexion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/gestion');

        // Non authentifie : l'entry point form_login redirige vers /connexion.
        self::assertResponseRedirects('/connexion');
    }

    public function test_un_emprunteur_est_interdit(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'emprunteur.' . uniqid() . '@cnam-reunion.fr';
        $this->purger($em, $email);
        $u = $this->creerUtilisateur($em, $hasher, $email, Role::EMPRUNTEUR);

        $client->loginUser($u);
        $client->request('GET', '/gestion');

        // Authentifie mais sans ROLE_GESTIONNAIRE : 403 Forbidden.
        self::assertResponseStatusCodeSame(403);

        $this->purger($em, $email);
    }

    public function test_un_gestionnaire_accede(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'gestion.' . uniqid() . '@cnam-reunion.fr';
        $this->purger($em, $email);
        $u = $this->creerUtilisateur($em, $hasher, $email, Role::GESTIONNAIRE);

        $client->loginUser($u);
        $client->request('GET', '/gestion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Espace gestion');

        $this->purger($em, $email);
    }

    public function test_le_tableau_de_bord_expose_les_liens_crud(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'gestion.liens.' . uniqid() . '@cnam-reunion.fr';
        $this->purger($em, $email);
        $u = $this->creerUtilisateur($em, $hasher, $email, Role::GESTIONNAIRE);

        $client->loginUser($u);
        $client->request('GET', '/gestion');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/gestion/categorie"]');
        self::assertSelectorExists('a[href="/gestion/materiel"]');

        $this->purger($em, $email);
    }
}
