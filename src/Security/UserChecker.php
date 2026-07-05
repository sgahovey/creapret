<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse l'authentification d'un compte desactive (estActif = false).
 * Leve AVANT la verification du mot de passe : ne revele pas l'etat du compte
 * a un tiers (defense en profondeur). La desactivation en cours de session est
 * geree separement par Utilisateur::isEqualTo() (US-1.1).
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof Utilisateur && !$user->isEstActif()) {
            throw new DisabledException('Ce compte est desactive.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Aucune verification post-authentification pour l'instant.
    }
}
