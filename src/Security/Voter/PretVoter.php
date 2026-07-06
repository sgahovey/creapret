<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\StatutPret;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorisation au niveau de l'INSTANCE de pret (pas seulement du role) : un emprunteur ne peut
 * voir/annuler que SES prets. Empeche l'IDOR (acces a un pret d'autrui via un id forge).
 *
 * @extends Voter<string, Pret>
 */
final class PretVoter extends Voter
{
    public const VOIR = 'VOIR';
    public const ANNULER = 'ANNULER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VOIR, self::ANNULER], true) && $subject instanceof Pret;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // $subject est un Pret (garanti par supports()).
        $utilisateur = $token->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return false;
        }

        // Regle commune : le pret doit appartenir a l'utilisateur.
        if ($subject->getEmprunteur() !== $utilisateur) {
            return false;
        }

        return match ($attribute) {
            self::VOIR => true,
            // On n'annule qu'une demande encore en attente (cycle de vie US-3.1).
            self::ANNULER => StatutPret::DEMANDE === $subject->getStatut(),
            default       => false,
        };
    }
}
