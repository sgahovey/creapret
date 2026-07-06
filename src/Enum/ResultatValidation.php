<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Issue d'une tentative de validation d'un pret (CU-07).
 *
 * VALIDE : la reverification sous verrou n'a trouve aucun pret VALIDE chevauchant ; le pret
 * passe a l'etat VALIDE. CONFLIT : un pret VALIDE chevauche deja (RG-1) ; le pret est refuse
 * pour conflit. DEJA_TRAITE : le pret n'etait plus au statut DEMANDE (deja valide/refuse par
 * un autre gestionnaire) ; aucune action.
 */
enum ResultatValidation
{
    case VALIDE;
    case CONFLIT;
    case DEJA_TRAITE;
}
