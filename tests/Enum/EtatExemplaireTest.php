<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\EtatExemplaire;
use PHPUnit\Framework\TestCase;

final class EtatExemplaireTest extends TestCase
{
    public function test_valeurs_backed_sans_accent(): void
    {
        // Verrou des valeurs persistees. Comparaison indexee : evite le narrowing
        // statique PHPStan (deux unions comparees, pas un litteral « toujours vrai »).
        $attendues = ['disponible', 'prete', 'en_maintenance', 'hors_service', 'perdu'];

        foreach (EtatExemplaire::cases() as $i => $etat) {
            self::assertSame($attendues[$i], $etat->value);
        }
    }

    public function test_libelle_et_badge(): void
    {
        self::assertSame('Disponible', EtatExemplaire::DISPONIBLE->libelle());
        self::assertSame('success', EtatExemplaire::DISPONIBLE->couleurBadge());
        self::assertSame('danger', EtatExemplaire::PERDU->couleurBadge());
    }

    public function test_seul_disponible_est_pretable(): void
    {
        self::assertTrue(EtatExemplaire::DISPONIBLE->estPretable());
        self::assertFalse(EtatExemplaire::PRETE->estPretable());
        self::assertFalse(EtatExemplaire::EN_MAINTENANCE->estPretable());
        self::assertFalse(EtatExemplaire::HORS_SERVICE->estPretable());
        self::assertFalse(EtatExemplaire::PERDU->estPretable());
    }
}
