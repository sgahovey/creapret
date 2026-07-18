<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\TypeActionJournal;
use PHPUnit\Framework\TestCase;

final class TypeActionJournalTest extends TestCase
{
    public function test_valeurs_backed_persistees(): void
    {
        // Contrat DB (journal_admin.type_action). Comparaison indexee : evite le narrowing statique
        // PHPStan (deux unions comparees, pas un litteral « toujours vrai »), cf. RoleTest. Toutes les
        // valeurs sont en MAJUSCULES (coherence interne de l'enum, cf. DT-7).
        $attendus = [
            'PRET_VALIDATION',
            'PRET_REFUS',
            'PRET_RETOUR',
            'COMPTE_CREATION',
            'COMPTE_MODIFICATION',
            'COMPTE_CHANGEMENT_ROLE',
            'COMPTE_ACTIVATION',
            'COMPTE_DESACTIVATION',
        ];

        foreach (TypeActionJournal::cases() as $i => $cas) {
            self::assertSame($attendus[$i], $cas->value);
        }
    }

    public function test_libelles_accentues(): void
    {
        self::assertSame('Validation de prêt', TypeActionJournal::PRET_VALIDATION->libelle());
        self::assertSame('Création de compte', TypeActionJournal::COMPTE_CREATION->libelle());
        self::assertSame('Modification de compte', TypeActionJournal::COMPTE_MODIFICATION->libelle());
        self::assertSame('Changement de rôle', TypeActionJournal::COMPTE_CHANGEMENT_ROLE->libelle());
        self::assertSame('Activation de compte', TypeActionJournal::COMPTE_ACTIVATION->libelle());
        self::assertSame('Désactivation de compte', TypeActionJournal::COMPTE_DESACTIVATION->libelle());
    }
}
