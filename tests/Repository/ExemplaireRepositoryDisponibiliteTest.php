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

final class ExemplaireRepositoryDisponibiliteTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-libre';

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
            ->setEmail('libre.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('T')->setPrenom('L')
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
            ->setParameter('p', 'libre.%')->execute();
    }

    private function exemplaire(string $suffixe, EtatExemplaire $etat = EtatExemplaire::DISPONIBLE): Exemplaire
    {
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-' . $suffixe)
            ->setEtat($etat)
            ->setMateriel($this->materiel);
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

    private function compter(string $debut, string $fin): int
    {
        return $this->repo->compterLibresSurPeriode(
            $this->materiel,
            new \DateTimeImmutable($debut),
            new \DateTimeImmutable($fin),
        );
    }

    public function test_tous_libres_si_aucun_pret(): void
    {
        $this->exemplaire('a');
        $this->exemplaire('b');
        $this->exemplaire('c');

        self::assertSame(3, $this->compter('2026-09-10 00:00:00', '2026-09-15 00:00:00'));
    }

    public function test_un_exemplaire_prete_sur_la_periode_est_exclu(): void
    {
        $a = $this->exemplaire('a');
        $this->exemplaire('b');
        $this->exemplaire('c');
        // a est prete du 10 au 20 : indisponible pour une demande 12->15.
        $this->pretValide($a, '2026-09-10 00:00:00', '2026-09-20 00:00:00');

        self::assertSame(2, $this->compter('2026-09-12 00:00:00', '2026-09-15 00:00:00'));
    }

    public function test_un_pret_hors_periode_ne_reduit_pas_le_compte(): void
    {
        $a = $this->exemplaire('a');
        $this->exemplaire('b');
        // a est prete en aout : n'affecte pas une demande en septembre.
        $this->pretValide($a, '2026-08-01 00:00:00', '2026-08-10 00:00:00');

        self::assertSame(2, $this->compter('2026-09-12 00:00:00', '2026-09-15 00:00:00'));
    }

    public function test_un_exemplaire_en_maintenance_ne_compte_pas(): void
    {
        $this->exemplaire('a');
        $this->exemplaire('b', EtatExemplaire::EN_MAINTENANCE);
        // b n'est pas DISPONIBLE : exclu meme sans aucun pret.

        self::assertSame(1, $this->compter('2026-09-12 00:00:00', '2026-09-15 00:00:00'));
    }

    public function test_relais_le_meme_exemplaire_reste_libre(): void
    {
        $a = $this->exemplaire('a');
        // a est prete jusqu'au 12 ; une demande commencant le 12 (relais) le trouve libre.
        $this->pretValide($a, '2026-09-05 00:00:00', '2026-09-12 00:00:00');

        self::assertSame(1, $this->compter('2026-09-12 00:00:00', '2026-09-15 00:00:00'));
    }
}
