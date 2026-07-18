<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Types d'actions d'administration tracees dans le journal (journal_admin, US-5.3 et US-6.2).
 *
 * Deux familles : les decisions de pret prises par un gestionnaire (PRET_*, US-5.3) et les actions
 * de gestion des comptes par le super-administrateur (COMPTE_*, US-6.2 / BF-13). Chaque entree fige
 * l'acteur et, le cas echeant, la cible ; l'enumeration reste extensible sans changement de structure.
 *
 * Convention de casse : valeurs backed en MAJUSCULES (coherence interne de l'enum). C'est le seul
 * enum du projet dans ce cas (cf. DT-7) : realigner sur les minuscules imposerait une migration de
 * donnees sur journal_admin pour un gain purement stylistique.
 */
enum TypeActionJournal: string
{
    case PRET_VALIDATION = 'PRET_VALIDATION';
    case PRET_REFUS = 'PRET_REFUS';
    case PRET_RETOUR = 'PRET_RETOUR';
    case COMPTE_CREATION = 'COMPTE_CREATION';
    case COMPTE_MODIFICATION = 'COMPTE_MODIFICATION';
    case COMPTE_CHANGEMENT_ROLE = 'COMPTE_CHANGEMENT_ROLE';
    case COMPTE_ACTIVATION = 'COMPTE_ACTIVATION';
    case COMPTE_DESACTIVATION = 'COMPTE_DESACTIVATION';

    /** Libelle francais de l'action, pour l'affichage (badges, filtre). */
    public function libelle(): string
    {
        return match ($this) {
            self::PRET_VALIDATION        => 'Validation de prêt',
            self::PRET_REFUS             => 'Refus de prêt',
            self::PRET_RETOUR            => 'Enregistrement de retour',
            self::COMPTE_CREATION        => 'Création de compte',
            self::COMPTE_MODIFICATION    => 'Modification de compte',
            self::COMPTE_CHANGEMENT_ROLE => 'Changement de rôle',
            self::COMPTE_ACTIVATION      => 'Activation de compte',
            self::COMPTE_DESACTIVATION   => 'Désactivation de compte',
        };
    }
}
