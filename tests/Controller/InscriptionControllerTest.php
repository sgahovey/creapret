<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InscriptionControllerTest extends WebTestCase
{
    private function purgerUtilisateurs(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\Entity\Utilisateur')->execute();
    }

    public function test_la_page_inscription_est_accessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/inscription');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Creer un compte');
    }

    public function test_inscription_valide_cree_un_emprunteur_avec_consentement(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purgerUtilisateurs($em);

        $client->request('GET', '/inscription');
        $client->submitForm('Creer mon compte', [
            'inscription[email]'         => 'emprunteur@cnam-reunion.fr',
            'inscription[prenom]'        => 'Marie',
            'inscription[nom]'           => 'Payet',
            'inscription[plainPassword]' => 'Motdepasse1!',
            'inscription[accepteCgu]'    => true,
        ]);

        self::assertResponseRedirects();

        $repo = static::getContainer()->get(UtilisateurRepository::class);
        $u = $repo->findOneBy(['email' => 'emprunteur@cnam-reunion.fr']);

        self::assertInstanceOf(Utilisateur::class, $u);
        self::assertSame(Role::EMPRUNTEUR, $u->getRole());
        self::assertTrue($u->isEstActif());
        self::assertNotNull($u->getDateConsentement(), 'La preuve de consentement doit etre horodatee.');
        self::assertSame('1.0', $u->getVersionCgu());
        self::assertNotSame('Motdepasse1!', $u->getPassword(), 'Le mot de passe doit etre hache, jamais en clair.');
        self::assertStringStartsWith('$argon2id$', $u->getPassword());
    }

    public function test_mot_de_passe_faible_est_rejete(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purgerUtilisateurs($em);

        $client->request('GET', '/inscription');
        $client->submitForm('Creer mon compte', [
            'inscription[email]'         => 'faible@cnam-reunion.fr',
            'inscription[prenom]'        => 'Jean',
            'inscription[nom]'           => 'Hoarau',
            'inscription[plainPassword]' => 'faible',
            'inscription[accepteCgu]'    => true,
        ]);

        self::assertResponseIsUnprocessable();
        $repo = static::getContainer()->get(UtilisateurRepository::class);
        self::assertNull($repo->findOneBy(['email' => 'faible@cnam-reunion.fr']));
    }

    public function test_cgu_non_cochee_est_rejetee(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purgerUtilisateurs($em);

        $client->request('GET', '/inscription');
        $client->submitForm('Creer mon compte', [
            'inscription[email]'         => 'sanscgu@cnam-reunion.fr',
            'inscription[prenom]'        => 'Luc',
            'inscription[nom]'           => 'Grondin',
            'inscription[plainPassword]' => 'Motdepasse1!',
            'inscription[accepteCgu]'    => false,
        ]);

        self::assertResponseIsUnprocessable();
        $repo = static::getContainer()->get(UtilisateurRepository::class);
        self::assertNull($repo->findOneBy(['email' => 'sanscgu@cnam-reunion.fr']));
    }

    public function test_email_deja_utilise_est_rejete(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purgerUtilisateurs($em);

        $existant = (new Utilisateur())
            ->setEmail('doublon@cnam-reunion.fr')
            ->setNom('Test')
            ->setPrenom('Un')
            ->setMotDePasseHash('$argon2id$fake');
        $em->persist($existant);
        $em->flush();

        $client->request('GET', '/inscription');
        $client->submitForm('Creer mon compte', [
            'inscription[email]'         => 'doublon@cnam-reunion.fr',
            'inscription[prenom]'        => 'Deux',
            'inscription[nom]'           => 'Test',
            'inscription[plainPassword]' => 'Motdepasse1!',
            'inscription[accepteCgu]'    => true,
        ]);

        self::assertResponseIsUnprocessable();
        $repo = static::getContainer()->get(UtilisateurRepository::class);
        self::assertCount(1, $repo->findBy(['email' => 'doublon@cnam-reunion.fr']));
    }
}
