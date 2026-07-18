<?php

declare(strict_types=1);

namespace App\Tests\Controller\Gestion;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifie l'acces au tableau de bord (US-5.1), destine au gestionnaire (aide a la decision).
 * Le super-administrateur y accede par heritage de role (hierarchie cumulative) ; l'emprunteur,
 * qui n'a pas ROLE_GESTIONNAIRE, recoit un 403. Double barriere : access_control ^/gestion ET
 * #[IsGranted('ROLE_GESTIONNAIRE')] sur le controleur.
 */
final class TableauDeBordControllerTest extends WebTestCase
{
    private const MARQUEUR = 'dashtdb.';
    private const URL = '/gestion/tableau-de-bord';

    public function test_gestionnaire_accede_au_tableau_de_bord(): void
    {
        $client = static::createClient();
        $client->loginUser($this->utilisateur($client, Role::GESTIONNAIRE));

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tableau de bord');
    }

    public function test_super_admin_accede_par_heritage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->utilisateur($client, Role::SUPER_ADMIN));

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
    }

    public function test_emprunteur_est_interdit(): void
    {
        $client = static::createClient();
        $client->loginUser($this->utilisateur($client, Role::EMPRUNTEUR));

        $client->request('GET', self::URL);

        self::assertResponseStatusCodeSame(403);
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }

    private function utilisateur(KernelBrowser $client, Role $role): Utilisateur
    {
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $utilisateur = (new Utilisateur())
            ->setEmail(self::MARQUEUR . uniqid() . '@creapret.local')
            ->setNom('Test')
            ->setPrenom('Tableau')
            ->setRole($role)
            ->setEstActif(true);
        $utilisateur->setMotDePasseHash($hasher->hashPassword($utilisateur, 'motdepasse'));

        $em = $this->em($client);
        $em->persist($utilisateur);
        $em->flush();

        return $utilisateur;
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :prefixe')
            ->setParameter('prefixe', self::MARQUEUR . '%')
            ->execute();

        parent::tearDown();
    }
}
