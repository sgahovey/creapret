<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\StatutPret;
use PHPUnit\Framework\TestCase;

final class StatutPretTest extends TestCase
{
    public function test_les_etats_terminaux(): void
    {
        // Terminaux : plus aucune transition possible.
        self::assertTrue(StatutPret::REFUSE->estTerminal());
        self::assertTrue(StatutPret::ANNULE->estTerminal());
        self::assertTrue(StatutPret::RETOURNE->estTerminal());

        // Non terminaux : le pret peut encore evoluer.
        self::assertFalse(StatutPret::DEMANDE->estTerminal());
        self::assertFalse(StatutPret::VALIDE->estTerminal());
    }

    public function test_les_valeurs_backed_sont_stables(): void
    {
        // Ces valeurs sont stockees en base (VARCHAR) : elles ne doivent pas changer.
        // Comparaison indexee : evite le narrowing statique PHPStan (deux unions).
        $attendues = ['demande', 'valide', 'refuse', 'retourne', 'annule'];

        foreach (StatutPret::cases() as $i => $statut) {
            self::assertSame($attendues[$i], $statut->value);
        }
    }

    public function test_libelle_et_couleur(): void
    {
        self::assertSame('En attente', StatutPret::DEMANDE->libelle());
        self::assertSame('warning', StatutPret::DEMANDE->couleurBadge());
        self::assertSame('success', StatutPret::VALIDE->couleurBadge());
        self::assertSame('danger', StatutPret::REFUSE->couleurBadge());
    }
}
