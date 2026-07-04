<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_valeurs_metier(): void
    {
        // Verrou des valeurs persistees en base (contrat DB). La comparaison
        // indexee evite le narrowing statique PHPStan (deux unions comparees,
        // pas un litteral unique « toujours vrai »).
        $attendus = ['emprunteur', 'gestionnaire', 'super_admin'];

        foreach (Role::cases() as $i => $role) {
            self::assertSame($attendus[$i], $role->value);
        }
    }

    public function test_libelle_et_badge(): void
    {
        self::assertSame('Super-administrateur', Role::SUPER_ADMIN->libelle());
        self::assertStringStartsWith('text-bg-', Role::GESTIONNAIRE->couleurBadge());
    }
}
