<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Enum\EtatExemplaire;
use PHPUnit\Framework\TestCase;

final class ExemplaireTest extends TestCase
{
    public function test_etat_disponible_par_defaut(): void
    {
        $e = new Exemplaire();

        self::assertSame(EtatExemplaire::DISPONIBLE, $e->getEtat());
    }

    public function test_accesseurs(): void
    {
        $m = (new Materiel())->setNom('Videoprojecteur Epson');
        $e = (new Exemplaire())
            ->setNumeroInventaire('INV-2026-001')
            ->setEtat(EtatExemplaire::EN_MAINTENANCE)
            ->setMateriel($m);

        self::assertSame('INV-2026-001', $e->getNumeroInventaire());
        self::assertSame(EtatExemplaire::EN_MAINTENANCE, $e->getEtat());
        self::assertSame($m, $e->getMateriel());
        self::assertNull($e->getId());
    }

    public function test_relation_bidirectionnelle_avec_materiel(): void
    {
        $m = (new Materiel())->setNom('Dell Latitude');
        $e = (new Exemplaire())->setNumeroInventaire('INV-2026-002');

        $m->addExemplaire($e);

        self::assertCount(1, $m->getExemplaires());
        self::assertSame($m, $e->getMateriel());
    }
}
