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
use App\Repository\PretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PretRepositoryTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-chevauche';

    private EntityManagerInterface $em;
    private PretRepository $repo;
    private Exemplaire $exemplaire;
    private Utilisateur $emprunteur;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(PretRepository::class);
        $this->purger();

        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $this->em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $this->em->persist($mat);
        $this->exemplaire = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-ex')
            ->setEtat(EtatExemplaire::DISPONIBLE)
            ->setMateriel($mat);
        $this->em->persist($this->exemplaire);
        $this->emprunteur = (new Utilisateur())
            ->setEmail('chevauche.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('T')->setPrenom('C')
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
            ->setParameter('p', 'chevauche.%')->execute();
    }

    private function creerPret(string $debut, string $fin, StatutPret $statut = StatutPret::VALIDE): void
    {
        $pret = (new Pret())
            ->setExemplaire($this->exemplaire)
            ->setEmprunteur($this->emprunteur)
            ->setDateDebut(new \DateTimeImmutable($debut))
            ->setDateFin(new \DateTimeImmutable($fin))
            ->setStatut($statut);
        $this->em->persist($pret);
        $this->em->flush();
    }

    private function chevauche(string $debut, string $fin): bool
    {
        return $this->repo->existePretChevauchant(
            $this->exemplaire,
            new \DateTimeImmutable($debut),
            new \DateTimeImmutable($fin),
        );
    }

    // Pret de reference : du 10 au 20 septembre.
    public function test_periode_disjointe_avant_ne_chevauche_pas(): void
    {
        $this->creerPret('2026-09-10 00:00:00', '2026-09-20 00:00:00');
        self::assertFalse($this->chevauche('2026-09-01 00:00:00', '2026-09-05 00:00:00'));
    }

    public function test_periode_disjointe_apres_ne_chevauche_pas(): void
    {
        $this->creerPret('2026-09-10 00:00:00', '2026-09-20 00:00:00');
        self::assertFalse($this->chevauche('2026-09-25 00:00:00', '2026-09-30 00:00:00'));
    }

    public function test_chevauchement_partiel_par_la_gauche(): void
    {
        $this->creerPret('2026-09-10 00:00:00', '2026-09-20 00:00:00');
        // demande 05->15 : deborde sur le debut du pret.
        self::assertTrue($this->chevauche('2026-09-05 00:00:00', '2026-09-15 00:00:00'));
    }

    public function test_chevauchement_partiel_par_la_droite(): void
    {
        $this->creerPret('2026-09-10 00:00:00', '2026-09-20 00:00:00');
        // demande 15->25 : deborde sur la fin du pret.
        self::assertTrue($this->chevauche('2026-09-15 00:00:00', '2026-09-25 00:00:00'));
    }

    public function test_periode_incluse_dans_le_pret(): void
    {
        $this->creerPret('2026-09-10 00:00:00', '2026-09-20 00:00:00');
        self::assertTrue($this->chevauche('2026-09-12 00:00:00', '2026-09-15 00:00:00'));
    }

    public function test_periode_englobant_le_pret(): void
    {
        $this->creerPret('2026-09-10 00:00:00', '2026-09-20 00:00:00');
        self::assertTrue($this->chevauche('2026-09-05 00:00:00', '2026-09-25 00:00:00'));
    }

    public function test_relais_fin_egale_debut_ne_chevauche_pas(): void
    {
        $this->creerPret('2026-09-10 00:00:00', '2026-09-20 00:00:00');
        // demande commencant exactement a la fin du pret : relais autorise (inegalites strictes).
        self::assertFalse($this->chevauche('2026-09-20 00:00:00', '2026-09-25 00:00:00'));
    }

    public function test_relais_debut_egale_fin_ne_chevauche_pas(): void
    {
        $this->creerPret('2026-09-10 00:00:00', '2026-09-20 00:00:00');
        // demande finissant exactement au debut du pret.
        self::assertFalse($this->chevauche('2026-09-05 00:00:00', '2026-09-10 00:00:00'));
    }

    public function test_un_pret_non_valide_ne_bloque_pas(): void
    {
        // Une DEMANDE en attente (non validee) ne reserve pas l'exemplaire.
        $this->creerPret('2026-09-10 00:00:00', '2026-09-20 00:00:00', StatutPret::DEMANDE);
        self::assertFalse($this->chevauche('2026-09-12 00:00:00', '2026-09-15 00:00:00'));
    }
}
