<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Service\PretService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RetourPretTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-retour';

    private EntityManagerInterface $em;
    private PretService $service;
    private Utilisateur $emprunteur;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(PretService::class);
        $this->purger();

        $this->emprunteur = (new Utilisateur())
            ->setEmail('retour.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('T')->setPrenom('R')
            ->setRole(Role::EMPRUNTEUR)->setEstActif(true)->setMotDePasseHash('x');
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
            ->setParameter('p', 'retour.%')->execute();
    }

    private function pretValide(EtatExemplaire $etatExemplaire = EtatExemplaire::DISPONIBLE, StatutPret $statut = StatutPret::VALIDE): Pret
    {
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $this->em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $this->em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())
            ->setEtat($etatExemplaire)->setMateriel($mat);
        $this->em->persist($ex);
        $pret = (new Pret())->setExemplaire($ex)->setEmprunteur($this->emprunteur)
            ->setDateDebut(new \DateTimeImmutable('2026-09-10 00:00:00'))
            ->setDateFin(new \DateTimeImmutable('2026-09-15 00:00:00'))
            ->setStatut($statut);
        $this->em->persist($pret);
        $this->em->flush();

        return $pret;
    }

    public function test_retour_normal_libere_l_exemplaire(): void
    {
        $pret = $this->pretValide();

        $this->service->enregistrerRetour($pret, false);

        self::assertSame(StatutPret::RETOURNE, $pret->getStatut());
        self::assertNotNull($pret->getDateRetour());
        self::assertSame(EtatExemplaire::DISPONIBLE, $pret->getExemplaire()->getEtat());
    }

    public function test_retour_avec_dommage_met_l_exemplaire_en_maintenance(): void
    {
        $pret = $this->pretValide();

        $this->service->enregistrerRetour($pret, true);

        self::assertSame(StatutPret::RETOURNE, $pret->getStatut());
        self::assertSame(EtatExemplaire::EN_MAINTENANCE, $pret->getExemplaire()->getEtat());
    }

    public function test_un_pret_non_valide_n_est_pas_retourne(): void
    {
        // Un pret DEMANDE (non VALIDE) ne peut pas etre retourne.
        $pret = $this->pretValide(EtatExemplaire::DISPONIBLE, StatutPret::DEMANDE);

        $this->service->enregistrerRetour($pret, false);

        self::assertSame(StatutPret::DEMANDE, $pret->getStatut());
        self::assertNull($pret->getDateRetour());
    }
}
