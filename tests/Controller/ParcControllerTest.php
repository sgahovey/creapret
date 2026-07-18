<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ParcControllerTest extends WebTestCase
{
    private function gestionnaire(EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('gest.parc.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('Gest')
            ->setPrenom('Parc')
            ->setRole(Role::GESTIONNAIRE)
            ->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'Motdepasse1!'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function purger(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'gest.parc.%')->execute();
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purger($em);
        parent::tearDown();
    }

    public function test_un_anonyme_est_redirige_vers_la_connexion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/gestion/parc');

        self::assertResponseRedirects('/connexion');
    }

    public function test_le_gestionnaire_voit_l_etat_du_parc(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $client->loginUser($this->gestionnaire($em, $hasher));
        $client->request('GET', '/gestion/parc');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'État du parc');
    }
}
