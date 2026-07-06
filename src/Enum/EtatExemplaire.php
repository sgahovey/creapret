<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Etat d'inventaire d'un exemplaire physique.
 * Backed enum stocke en VARCHAR (DC-8) : surete de typage cote code,
 * souplesse en base (ajout de valeur sans ALTER). Valeurs sans accent
 * (securite d'encodage), libelle accentue via libelle().
 */
enum EtatExemplaire: string
{
    case DISPONIBLE = 'disponible';
    case PRETE = 'prete';
    case EN_MAINTENANCE = 'en_maintenance';
    case HORS_SERVICE = 'hors_service';
    case PERDU = 'perdu';

    /** Libelle lisible (accentue) pour l'affichage. */
    public function libelle(): string
    {
        return match ($this) {
            self::DISPONIBLE     => 'Disponible',
            self::PRETE          => 'Prete',
            self::EN_MAINTENANCE => 'En maintenance',
            self::HORS_SERVICE   => 'Hors service',
            self::PERDU          => 'Perdu',
        };
    }

    /** Classe de badge Bootstrap pour l'affichage de l'etat. */
    public function couleurBadge(): string
    {
        return match ($this) {
            self::DISPONIBLE     => 'success',
            self::PRETE          => 'primary',
            self::EN_MAINTENANCE => 'warning',
            self::HORS_SERVICE   => 'secondary',
            self::PERDU          => 'danger',
        };
    }

    /** Un exemplaire est-il empruntable dans cet etat ? (RG-2, utile des l'inventaire) */
    public function estPretable(): bool
    {
        return self::DISPONIBLE === $this;
    }
}
