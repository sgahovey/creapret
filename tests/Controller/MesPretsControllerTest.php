<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Repository\PretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MesPretsControllerTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-mes-prets';

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }

    private function purger(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\Entity\Pret p WHERE EXISTS (SELECT e2.id FROM App\Entity\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'mesprets.%')->execute();
    }

    private function emprunteur(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, string $suffixe): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('mesprets.' . $suffixe . '.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('T')->setPrenom($suffixe)
            ->setRole(Role::EMPRUNTEUR)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'motdepasse'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function pret(EntityManagerInterface $em, Utilisateur $emprunteur, StatutPret $statut = StatutPret::DEMANDE): Pret
    {
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $em->persist($ex);
        $pret = (new Pret())->setExemplaire($ex)->setEmprunteur($emprunteur)
            ->setDateDebut(new \DateTimeImmutable('2026-09-10 00:00:00'))
            ->setDateFin(new \DateTimeImmutable('2026-09-15 00:00:00'))
            ->setStatut($statut);
        $em->persist($pret);
        $em->flush();

        return $pret;
    }

    public function test_mes_prets_affiche_mes_prets(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $moi = $this->emprunteur($em, $hasher, 'moi');
        $this->pret($em, $moi);

        $client->loginUser($moi);
        $client->request('GET', '/pret/mes-prets');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mes prets');
    }

    public function test_annuler_ma_demande(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $moi = $this->emprunteur($em, $hasher, 'moi');
        $pret = $this->pret($em, $moi);
        $idPret = $pret->getId();

        $client->loginUser($moi);
        $client->request('GET', '/pret/mes-prets');
        $client->submitForm('Annuler');
        self::assertResponseRedirects('/pret/mes-prets');

        $em->clear();
        $relu = $client->getContainer()->get(PretRepository::class)->find($idPret);
        self::assertNotNull($relu);
        self::assertSame(StatutPret::ANNULE, $relu->getStatut());
    }

    public function test_idor_un_emprunteur_ne_peut_pas_annuler_le_pret_d_autrui(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $alice = $this->emprunteur($em, $hasher, 'alice');
        $bob = $this->emprunteur($em, $hasher, 'bob');
        $pretDeAlice = $this->pret($em, $alice);
        $idPret = $pretDeAlice->getId();

        // Bob se connecte et tente d'annuler le pret d'Alice en forgeant l'URL.
        $client->loginUser($bob);
        $client->request('POST', '/pret/mes-prets/' . $idPret . '/annuler', [
            '_token' => 'peu-importe',
        ]);

        // Le PretVoter doit bloquer : 403, et le pret d'Alice reste DEMANDE.
        self::assertResponseStatusCodeSame(403);
        $em->clear();
        $relu = $client->getContainer()->get(PretRepository::class)->find($idPret);
        self::assertNotNull($relu);
        self::assertSame(StatutPret::DEMANDE, $relu->getStatut());
    }

    protected function tearDown(): void
    {
        // Reutilise le kernel deja boote par le test (createClient ne doit etre appele qu'une fois).
        $this->purger(static::getContainer()->get(EntityManagerInterface::class));
        parent::tearDown();
    }
}
