<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Politique de mot de passe CréaPrêt (source unique, réutilisée inscription + changement MDP).
 * Aligne sur les recommandations ANSSI/OWASP (§8) : longueur + diversite de caracteres.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class MotDePasseFort extends Constraint
{
    public int $longueurMin = 12;

    public string $messageTropCourt = 'Le mot de passe doit contenir au moins {{ min }} caracteres.';
    public string $messageMinuscule = 'Le mot de passe doit contenir au moins une lettre minuscule.';
    public string $messageMajuscule = 'Le mot de passe doit contenir au moins une lettre majuscule.';
    public string $messageChiffre = 'Le mot de passe doit contenir au moins un chiffre.';
    public string $messageSpecial = 'Le mot de passe doit contenir au moins un caractere special.';
}
