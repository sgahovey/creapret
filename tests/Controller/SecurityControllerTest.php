<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityControllerTest extends WebTestCase
{
    private function creerUtilisateur(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        string $email,
        string $motDePasse,
        bool $estActif,
    ): Utilisateur {
        $u = (new Utilisateur())
            ->setEmail($email)
            ->setNom('Test')
            ->setPrenom('Connexion')
            ->setRole(Role::EMPRUNTEUR)
            ->setEstActif($estActif);
        $u->setMotDePasseHash($hasher->hashPassword($u, $motDePasse));
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

    public function test_la_page_connexion_est_accessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion');
    }

    public function test_connexion_valide_authentifie_l_utilisateur(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'valide.' . uniqid() . '@cnam-reunion.fr';
        $this->purger($em, $email);
        $this->creerUtilisateur($em, $hasher, $email, 'Motdepasse1!', true);

        $client->request('GET', '/connexion');
        $client->submitForm('Se connecter', [
            'email'    => $email,
            'password' => 'Motdepasse1!',
        ]);

        self::assertResponseRedirects();
        // Suivre la redirection : l'utilisateur doit etre authentifie.
        $client->followRedirect();
        self::assertNotNull($client->getContainer()->get('security.token_storage')->getToken());

        $this->purger($em, $email);
    }

    public function test_identifiants_invalides_sont_rejetes(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'mauvais.' . uniqid() . '@cnam-reunion.fr';
        $this->purger($em, $email);
        $this->creerUtilisateur($em, $hasher, $email, 'Motdepasse1!', true);

        $client->request('GET', '/connexion');
        $client->submitForm('Se connecter', [
            'email'    => $email,
            'password' => 'MauvaisMotDePasse9!',
        ]);

        // Echec d'authentification : redirection vers la page de connexion.
        self::assertResponseRedirects('/connexion');

        $this->purger($em, $email);
    }

    public function test_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'inactif.' . uniqid() . '@cnam-reunion.fr';
        $this->purger($em, $email);
        // Bon mot de passe MAIS compte desactive : c'est le UserChecker qui doit bloquer.
        $this->creerUtilisateur($em, $hasher, $email, 'Motdepasse1!', false);

        $client->request('GET', '/connexion');
        $client->submitForm('Se connecter', [
            'email'    => $email,
            'password' => 'Motdepasse1!',
        ]);

        // DisabledException -> echec d'authentification -> retour /connexion.
        self::assertResponseRedirects('/connexion');

        $this->purger($em, $email);
    }
}
