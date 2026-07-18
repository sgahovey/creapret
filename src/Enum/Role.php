<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Roles applicatifs de CréaPrêt (US-1.1).
 * Valeurs metier stockees en base ; le prefixe ROLE_ Symfony est derive
 * dans Utilisateur::getRoles(). Hierarchie : SUPER_ADMIN > GESTIONNAIRE > EMPRUNTEUR (§5).
 */
enum Role: string
{
    case EMPRUNTEUR = 'emprunteur';
    case GESTIONNAIRE = 'gestionnaire';
    case SUPER_ADMIN = 'super_admin';

    /** Libelle humain (FR) pour l'affichage. */
    public function libelle(): string
    {
        return match ($this) {
            self::EMPRUNTEUR   => 'Emprunteur',
            self::GESTIONNAIRE => 'Gestionnaire',
            self::SUPER_ADMIN  => 'Super-administrateur',
        };
    }

    /** Couleur de badge Bootstrap 5 (nue, a prefixer par text-bg- a l'affichage ; contrat commun
     * avec StatutPret::couleurBadge et EtatExemplaire::couleurBadge). */
    public function couleurBadge(): string
    {
        return match ($this) {
            self::EMPRUNTEUR   => 'secondary',
            self::GESTIONNAIRE => 'primary',
            self::SUPER_ADMIN  => 'danger',
        };
    }
}
