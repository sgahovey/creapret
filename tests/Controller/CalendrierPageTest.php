<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CalendrierPageTest extends WebTestCase
{
    private function utilisateur(KernelBrowser $client, Role $role): Utilisateur
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $u = (new Utilisateur())->setEmail('calpage.' . uniqid() . '@creapret.local')
            ->setNom('T')->setPrenom('E')->setRole($role)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'password'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    public function test_gestionnaire_accede_a_la_page_calendrier(): void
    {
        $client = static::createClient();
        $gestionnaire = $this->utilisateur($client, Role::GESTIONNAIRE);

        $client->loginUser($gestionnaire);
        $client->request('GET', '/gestion/prets/calendrier');

        self::assertResponseIsSuccessful();
        // Le point d'accroche Stimulus est present : la page est prete a monter le calendrier.
        self::assertSelectorExists('[data-controller="calendrier"]');
    }

    public function test_emprunteur_ne_peut_pas_acceder_au_calendrier(): void
    {
        $client = static::createClient();
        $emprunteur = $this->utilisateur($client, Role::EMPRUNTEUR);

        $client->loginUser($emprunteur);
        $client->request('GET', '/gestion/prets/calendrier');

        self::assertResponseStatusCodeSame(403);
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\\Entity\\Utilisateur u WHERE u.email LIKE :p')->setParameter('p', 'calpage.%')->execute();
        parent::tearDown();
    }
}
