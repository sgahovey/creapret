<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statuts du cycle de vie d'un pret.
 *
 * Valeurs backed en minuscules sans accent (stockage VARCHAR, DC-8) ; les libelles
 * accentues sont pour l'affichage. Transitions : DEMANDE -> VALIDE | REFUSE | ANNULE ;
 * VALIDE -> RETOURNE. REFUSE / ANNULE / RETOURNE sont terminaux.
 */
enum StatutPret: string
{
    case DEMANDE = 'demande';
    case VALIDE = 'valide';
    case REFUSE = 'refuse';
    case RETOURNE = 'retourne';
    case ANNULE = 'annule';

    public function libelle(): string
    {
        return match ($this) {
            self::DEMANDE  => 'En attente',
            self::VALIDE   => 'Validé',
            self::REFUSE   => 'Refusé',
            self::RETOURNE => 'Retourné',
            self::ANNULE   => 'Annulé',
        };
    }

    public function couleurBadge(): string
    {
        return match ($this) {
            self::DEMANDE  => 'warning',
            self::VALIDE   => 'success',
            self::REFUSE   => 'danger',
            self::RETOURNE => 'secondary',
            self::ANNULE   => 'secondary',
        };
    }

    /**
     * Etats terminaux : plus aucune transition possible.
     */
    public function estTerminal(): bool
    {
        return match ($this) {
            self::REFUSE, self::ANNULE, self::RETOURNE => true,
            self::DEMANDE, self::VALIDE                => false,
        };
    }
}
