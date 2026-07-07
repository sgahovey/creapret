<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CalendrierApiControllerTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-calendrier';

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }

    private function purger(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\\Entity\\Pret p WHERE EXISTS (SELECT e2.id FROM App\\Entity\\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Exemplaire e WHERE e.numeroInventaire LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Materiel m WHERE m.nom LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Categorie c WHERE c.nom LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Utilisateur u WHERE u.email LIKE :p')->setParameter('p', 'calendrier.%')->execute();
    }

    private function utilisateur(KernelBrowser $client, Role $role): Utilisateur
    {
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $u = (new Utilisateur())->setEmail('calendrier.' . uniqid() . '@creapret.local')
            ->setNom('T')->setPrenom('E')->setRole($role)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'motdepasse'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function pretValide(KernelBrowser $client): void
    {
        $em = $this->em($client);
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-c');
        $em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-m')->setCategorie($cat);
        $em->persist($mat);
        $ex = (new Exemplaire())->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $em->persist($ex);
        $emp = $this->utilisateur($client, Role::EMPRUNTEUR);
        $pret = (new Pret())->setExemplaire($ex)->setEmprunteur($emp)
            ->setDateDebut(new \DateTimeImmutable('2026-09-12'))->setDateFin(new \DateTimeImmutable('2026-09-14'))
            ->setStatut(StatutPret::VALIDE);
        $em->persist($pret);
        $em->flush();
    }

    public function test_gestionnaire_recoit_les_evenements_en_json(): void
    {
        $client = static::createClient();
        $this->purger($this->em($client));
        $this->pretValide($client);
        $gestionnaire = $this->utilisateur($client, Role::GESTIONNAIRE);

        $client->loginUser($gestionnaire);
        $client->request('GET', '/gestion/api/calendrier/prets?start=2026-09-01&end=2026-09-30');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertGreaterThanOrEqual(1, count($data));
        self::assertArrayHasKey('title', $data[0]);
        // Le no-store est bien pose.
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function test_emprunteur_est_interdit_403(): void
    {
        $client = static::createClient();
        $this->purger($this->em($client));
        $emprunteur = $this->utilisateur($client, Role::EMPRUNTEUR);

        $client->loginUser($emprunteur);
        $client->request('GET', '/gestion/api/calendrier/prets?start=2026-09-01&end=2026-09-30');

        // Vue d'occupation globale reservee aux gestionnaires.
        self::assertResponseStatusCodeSame(403);
    }

    public function test_dates_invalides_renvoient_400(): void
    {
        $client = static::createClient();
        $this->purger($this->em($client));
        $gestionnaire = $this->utilisateur($client, Role::GESTIONNAIRE);

        $client->loginUser($gestionnaire);
        $client->request('GET', '/gestion/api/calendrier/prets?start=pas-une-date&end=non-plus');

        self::assertResponseStatusCodeSame(400);
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purger($em);
        parent::tearDown();
    }
}
