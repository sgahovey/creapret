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

final class RetourGestionTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-retour-gest';

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
            ->setParameter('p', 'retourgest.%')->execute();
    }

    private function utilisateur(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, Role $role, string $suffixe): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('retourgest.' . $suffixe . '.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('T')->setPrenom($suffixe)
            ->setRole($role)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'motdepasse'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function pretValide(EntityManagerInterface $em, Utilisateur $emprunteur): Pret
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
            ->setStatut(StatutPret::VALIDE);
        $em->persist($pret);
        $em->flush();

        return $pret;
    }

    public function test_un_emprunteur_n_accede_pas_aux_retours(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $client->loginUser($this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp'));
        $client->request('GET', '/gestion/prets/retours');
        self::assertResponseStatusCodeSame(403);
    }

    public function test_retour_normal_libere_l_exemplaire(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $pret = $this->pretValide($em, $emprunteur);
        $idPret = $pret->getId();
        $idEx = $pret->getExemplaire()->getId();

        $client->loginUser($this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest'));
        $client->request('GET', '/gestion/prets/retours');
        self::assertResponseIsSuccessful();

        // Retour sans cocher 'materiel endommage'.
        $client->submitForm('Enregistrer le retour');
        self::assertResponseRedirects('/gestion/prets/retours');

        $em->clear();
        $relu = $client->getContainer()->get(PretRepository::class)->find($idPret);
        self::assertNotNull($relu);
        self::assertSame(StatutPret::RETOURNE, $relu->getStatut());
        $ex = $em->getRepository(Exemplaire::class)->find($idEx);
        self::assertNotNull($ex);
        self::assertSame(EtatExemplaire::DISPONIBLE, $ex->getEtat());
    }

    public function test_retour_avec_dommage_met_en_maintenance(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $pret = $this->pretValide($em, $emprunteur);
        $idEx = $pret->getExemplaire()->getId();

        $client->loginUser($this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest'));
        $client->request('GET', '/gestion/prets/retours');

        // Coche 'materiel endommage' puis soumet.
        $client->submitForm('Enregistrer le retour', ['dommage' => '1']);
        self::assertResponseRedirects('/gestion/prets/retours');

        $em->clear();
        $ex = $em->getRepository(Exemplaire::class)->find($idEx);
        self::assertNotNull($ex);
        self::assertSame(EtatExemplaire::EN_MAINTENANCE, $ex->getEtat());
    }

    protected function tearDown(): void
    {
        // Reutilise le kernel deja boote par le test (createClient ne doit etre appele qu'une fois).
        $this->purger(static::getContainer()->get(EntityManagerInterface::class));
        parent::tearDown();
    }
}
