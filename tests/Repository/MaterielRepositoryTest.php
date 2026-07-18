<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Enum\EtatExemplaire;
use App\Repository\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MaterielRepositoryTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-parc';

    private EntityManagerInterface $em;
    private MaterielRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repo = self::getContainer()->get(MaterielRepository::class);
        $this->purger();
    }

    protected function tearDown(): void
    {
        $this->purger();
        parent::tearDown();
    }

    private function purger(): void
    {
        // Ordre impose par les FK : exemplaires -> materiels -> categories.
        $this->em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
    }

    public function test_etat_du_parc_compte_les_exemplaires_par_etat(): void
    {
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $this->em->persist($cat);

        // Materiel A : 2 disponibles + 1 en maintenance.
        $a = (new Materiel())->setNom(self::MARQUEUR . '-mat-A')->setCategorie($cat);
        $this->em->persist($a);
        foreach ([EtatExemplaire::DISPONIBLE, EtatExemplaire::DISPONIBLE, EtatExemplaire::EN_MAINTENANCE] as $i => $etat) {
            $this->em->persist(
                (new Exemplaire())
                    ->setNumeroInventaire(self::MARQUEUR . '-a-' . $i)
                    ->setEtat($etat)
                    ->setMateriel($a),
            );
        }

        // Materiel B : aucun exemplaire (doit apparaitre a zero via le LEFT JOIN).
        $b = (new Materiel())->setNom(self::MARQUEUR . '-mat-B')->setCategorie($cat);
        $this->em->persist($b);

        $this->em->flush();

        // On isole nos materiels marques (l'ordre est nom ASC : A avant B).
        $parc = array_values(array_filter(
            $this->repo->etatDuParc(),
            static fn (array $ligne) => str_starts_with($ligne['materiel']->getNom(), self::MARQUEUR),
        ));

        self::assertCount(2, $parc);

        // Materiel A.
        self::assertSame(self::MARQUEUR . '-mat-A', $parc[0]['materiel']->getNom());
        self::assertSame(2, $parc[0]['etats'][EtatExemplaire::DISPONIBLE->value]);
        self::assertSame(1, $parc[0]['etats'][EtatExemplaire::EN_MAINTENANCE->value]);
        self::assertSame(0, $parc[0]['etats'][EtatExemplaire::PRETE->value]);
        self::assertSame(3, $parc[0]['total']);

        // Materiel B : sans exemplaire, tout a zero.
        self::assertSame(self::MARQUEUR . '-mat-B', $parc[1]['materiel']->getNom());
        self::assertSame(0, $parc[1]['total']);
        self::assertSame(0, $parc[1]['etats'][EtatExemplaire::DISPONIBLE->value]);
    }
}
