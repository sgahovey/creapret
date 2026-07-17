<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\JournalAdmin;
use App\Enum\TypeActionJournal;
use App\Repository\JournalAdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test d'integration de JournalAdminRepository (US-5.3) : pagination/filtre de consultation et bornes
 * de purge. Transaction annulee en tearDown : aucune pollution de la base.
 */
final class JournalAdminRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private JournalAdminRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repo = self::getContainer()->get(JournalAdminRepository::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    private function entree(TypeActionJournal $type): JournalAdmin
    {
        $e = new JournalAdmin($type, 1, 'Acteur Test', 2, 'Cible Test');
        $this->em->persist($e);

        return $e;
    }

    public function test_find_pour_admin_filtre_par_type_et_pagine(): void
    {
        // On borne le comptage aux traces de ce test via un filtre par type dedie.
        $this->entree(TypeActionJournal::PRET_VALIDATION);
        $this->entree(TypeActionJournal::PRET_VALIDATION);
        $this->entree(TypeActionJournal::PRET_REFUS);
        $this->em->flush();

        $validations = $this->repo->findPourAdmin(1, 25, TypeActionJournal::PRET_VALIDATION);
        self::assertGreaterThanOrEqual(2, iterator_count($validations->getIterator()));

        foreach ($validations as $entree) {
            self::assertSame(TypeActionJournal::PRET_VALIDATION, $entree->getTypeAction());
        }
    }

    public function test_purger_avant_supprime_les_entrees_anterieures_au_seuil(): void
    {
        $entree = $this->entree(TypeActionJournal::PRET_RETOUR);
        $this->em->flush();

        // Rien avant hier (l'entree vient d'etre creee).
        $hier = new \DateTimeImmutable('-1 day');
        self::assertSame(0, $this->repo->compterAvant($hier));

        // Tout avant demain (inclut l'entree).
        $demain = new \DateTimeImmutable('+1 day');
        self::assertGreaterThanOrEqual(1, $this->repo->compterAvant($demain));

        $supprimes = $this->repo->purgerAvant($demain);
        self::assertGreaterThanOrEqual(1, $supprimes);
    }
}
