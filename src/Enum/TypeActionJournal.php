<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Types d'actions d'administration tracees dans le journal (journal_admin, US-5.3).
 *
 * Le journal enregistre les decisions prises par un gestionnaire sur les prets d'autrui.
 * Enumeration extensible : la gestion des comptes (BF-13) pourra ajouter des valeurs COMPTE_*
 * sans changement de structure.
 */
enum TypeActionJournal: string
{
    case PRET_VALIDATION = 'PRET_VALIDATION';
    case PRET_REFUS = 'PRET_REFUS';
    case PRET_RETOUR = 'PRET_RETOUR';

    /** Libelle francais de l'action, pour l'affichage (badges, filtre). */
    public function libelle(): string
    {
        return match ($this) {
            self::PRET_VALIDATION => 'Validation de prêt',
            self::PRET_REFUS      => 'Refus de prêt',
            self::PRET_RETOUR     => 'Enregistrement de retour',
        };
    }
}
