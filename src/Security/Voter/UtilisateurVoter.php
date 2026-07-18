<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorisation des actions d'administration sur un compte (US-6.2). Toutes exigent que l'acteur soit
 * un super-administrateur authentifie : le pare-feu ^/admin le garantit deja, mais le Voter ne le
 * presuppose pas (defense en profondeur). Des gardes anti-soi empechent un super-administrateur de
 * se verrouiller lui-meme hors de l'application.
 *
 * Le Voter reste PUR : aucune dependance a un repository. L'invariant global « au moins un
 * super-administrateur actif subsiste » releve d'un etat de la base ; il est verifie cote controleur
 * (bout 3), pas ici, pour garder le Voter sans effet de bord ni requete.
 *
 * @extends Voter<string, Utilisateur>
 */
final class UtilisateurVoter extends Voter
{
    public const VOIR = 'VOIR';
    public const MODIFIER = 'MODIFIER';
    public const CHANGER_ROLE = 'CHANGER_ROLE';
    public const ACTIVER = 'ACTIVER';
    public const DESACTIVER = 'DESACTIVER';

    private const ATTRIBUTS = [self::VOIR, self::MODIFIER, self::CHANGER_ROLE, self::ACTIVER, self::DESACTIVER];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTS, true) && $subject instanceof Utilisateur;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // $subject est le compte cible (garanti par supports()).
        $acteur = $token->getUser();
        if (!$acteur instanceof Utilisateur) {
            return false;
        }

        // Toutes les actions d'administration de compte sont reservees au super-administrateur.
        if (Role::SUPER_ADMIN !== $acteur->getRole()) {
            return false;
        }

        // Garde anti-soi : un super-administrateur ne peut ni se retirer son propre role, ni
        // (des)activer son propre compte -- cela le verrouillerait hors de l'application. Comparaison
        // sur l'identifiant (deux instances distinctes du meme compte restent « soi-meme »).
        $estSoiMeme = $subject->getId() === $acteur->getId();

        return match ($attribute) {
            // Consultation et modification de l'identite (nom, email) : sans danger, y compris sur soi.
            self::VOIR, self::MODIFIER => true,
            // Changement de role et (des)activation : interdits sur soi-meme (anti lock-out), permis
            // sur autrui.
            self::CHANGER_ROLE, self::ACTIVER, self::DESACTIVER => !$estSoiMeme,
            default                                             => false,
        };
    }
}
