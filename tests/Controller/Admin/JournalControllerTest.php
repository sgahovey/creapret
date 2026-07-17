<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifie l'acces au journal d'administration (US-5.3, BF-15), reserve au super-administrateur.
 * Double barriere : access_control ^/admin ET #[IsGranted('ROLE_SUPER_ADMIN')].
 */
final class JournalControllerTest extends WebTestCase
{
    private const MARQUEUR = 'journaltest.';

    public function test_super_admin_accede_au_journal(): void
    {
        $client = static::createClient();
        $client->loginUser($this->utilisateur($client, Role::SUPER_ADMIN));

        $client->request('GET', '/admin/journal');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Journal');
    }

    public function test_gestionnaire_est_interdit(): void
    {
        $client = static::createClient();
        $client->loginUser($this->utilisateur($client, Role::GESTIONNAIRE));

        $client->request('GET', '/admin/journal');

        self::assertResponseStatusCodeSame(403);
    }

    public function test_emprunteur_est_interdit(): void
    {
        $client = static::createClient();
        $client->loginUser($this->utilisateur($client, Role::EMPRUNTEUR));

        $client->request('GET', '/admin/journal');

        self::assertResponseStatusCodeSame(403);
    }

    private function utilisateur(KernelBrowser $client, Role $role): Utilisateur
    {
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $utilisateur = (new Utilisateur())
            ->setEmail(self::MARQUEUR . uniqid() . '@creapret.local')
            ->setNom('Test')
            ->setPrenom('Journal')
            ->setRole($role)
            ->setEstActif(true);
        $utilisateur->setMotDePasseHash($hasher->hashPassword($utilisateur, 'motdepasse'));

        $em = $client->getContainer()->get(EntityManagerInterface::class);
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
