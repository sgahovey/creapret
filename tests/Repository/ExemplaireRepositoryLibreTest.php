<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Repository\ExemplaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExemplaireRepositoryLibreTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-trouve';

    private EntityManagerInterface $em;
    private ExemplaireRepository $repo;
    private Materiel $materiel;
    private Utilisateur $emprunteur;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(ExemplaireRepository::class);
        $this->purger();

        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $this->em->persist($cat);
        $this->materiel = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $this->em->persist($this->materiel);
        $this->emprunteur = (new Utilisateur())
            ->setEmail('trouve.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('T')->setPrenom('R')
            ->setRole(Role::EMPRUNTEUR)->setEstActif(true)
            ->setMotDePasseHash('x');
        $this->em->persist($this->emprunteur);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->purger();
        parent::tearDown();
    }

    private function purger(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\Pret p WHERE EXISTS (SELECT e2.id FROM App\Entity\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'trouve.%')->execute();
    }

    private function exemplaire(string $suffixe, EtatExemplaire $etat = EtatExemplaire::DISPONIBLE): Exemplaire
    {
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-' . $suffixe)
            ->setEtat($etat)->setMateriel($this->materiel);
        $this->em->persist($ex);
        $this->em->flush();

        return $ex;
    }

    private function pretValide(Exemplaire $ex, string $debut, string $fin): void
    {
        $pret = (new Pret())
            ->setExemplaire($ex)->setEmprunteur($this->emprunteur)
            ->setDateDebut(new \DateTimeImmutable($debut))
            ->setDateFin(new \DateTimeImmutable($fin))
            ->setStatut(StatutPret::VALIDE);
        $this->em->persist($pret);
        $this->em->flush();
    }

    private function trouver(string $debut, string $fin): ?Exemplaire
    {
        return $this->repo->trouverUnLibreSurPeriode(
            $this->materiel,
            new \DateTimeImmutable($debut),
            new \DateTimeImmutable($fin),
        );
    }

    public function test_retourne_un_exemplaire_libre(): void
    {
        $this->exemplaire('a');

        $trouve = $this->trouver('2026-09-10 00:00:00', '2026-09-15 00:00:00');
        self::assertInstanceOf(Exemplaire::class, $trouve);
        self::assertSame(self::MARQUEUR . '-a', $trouve->getNumeroInventaire());
    }

    public function test_retourne_null_si_tous_pretes(): void
    {
        $a = $this->exemplaire('a');
        $this->pretValide($a, '2026-09-10 00:00:00', '2026-09-20 00:00:00');

        self::assertNull($this->trouver('2026-09-12 00:00:00', '2026-09-15 00:00:00'));
    }

    public function test_saute_l_exemplaire_prete_et_prend_le_libre(): void
    {
        $a = $this->exemplaire('a');
        $this->exemplaire('b');
        $this->pretValide($a, '2026-09-10 00:00:00', '2026-09-20 00:00:00');

        $trouve = $this->trouver('2026-09-12 00:00:00', '2026-09-15 00:00:00');
        self::assertInstanceOf(Exemplaire::class, $trouve);
        self::assertSame(self::MARQUEUR . '-b', $trouve->getNumeroInventaire());
    }

    public function test_ignore_les_exemplaires_non_disponibles(): void
    {
        $this->exemplaire('a', EtatExemplaire::EN_MAINTENANCE);

        self::assertNull($this->trouver('2026-09-12 00:00:00', '2026-09-15 00:00:00'));
    }
}
