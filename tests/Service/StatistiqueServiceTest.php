<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\ExemplaireRepository;
use App\Repository\PretRepository;
use App\Service\StatistiqueService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StatistiqueServiceTest extends TestCase
{
    private MockObject&PretRepository $prets;
    private MockObject&ExemplaireRepository $exemplaires;
    private StatistiqueService $service;

    protected function setUp(): void
    {
        $this->prets = $this->createMock(PretRepository::class);
        $this->exemplaires = $this->createMock(ExemplaireRepository::class);
        $this->service = new StatistiqueService($this->prets, $this->exemplaires);
    }

    public function test_agrege_les_kpis_et_calcule_le_taux(): void
    {
        $this->prets->expects(self::once())->method('countPretsEnCours')->willReturn(7);
        $this->prets->expects(self::once())->method('countDemandesEnAttente')->willReturn(3);
        $this->prets->expects(self::once())->method('countPretsEnRetard')->willReturn(2);
        $this->prets->expects(self::once())->method('countExemplairesEngages')->willReturn(8);
        $this->exemplaires->expects(self::once())->method('countTotal')->willReturn(20);
        $this->prets->expects(self::once())->method('topMaterielsEmpruntes')
            ->willReturn([['materiel' => 'Videoprojecteur', 'total' => 12]]);

        $stats = $this->service->calculerTableauBord();

        self::assertSame(7, $stats->pretsEnCours);
        self::assertSame(3, $stats->demandesEnAttente);
        self::assertSame(2, $stats->pretsEnRetard);
        self::assertSame(8, $stats->exemplairesEngages);
        self::assertSame(20, $stats->exemplairesTotal);
        // 8 / 20 * 100 = 40.0
        self::assertSame(40.0, $stats->tauxOccupation);
        self::assertCount(1, $stats->topMateriels);
    }

    public function test_taux_occupation_garde_division_par_zero_parc_vide(): void
    {
        $this->prets->method('countPretsEnCours')->willReturn(0);
        $this->prets->method('countDemandesEnAttente')->willReturn(0);
        $this->prets->method('countPretsEnRetard')->willReturn(0);
        $this->prets->expects(self::once())->method('countExemplairesEngages')->willReturn(0);
        $this->exemplaires->expects(self::once())->method('countTotal')->willReturn(0);
        $this->prets->method('topMaterielsEmpruntes')->willReturn([]);

        $stats = $this->service->calculerTableauBord();

        // Parc vide : pas de division par zero, taux = 0.0.
        self::assertSame(0.0, $stats->tauxOccupation);
    }
}
