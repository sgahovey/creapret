<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Security\Voter\UtilisateurVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Test unitaire du Voter d'administration des comptes (US-6.2) : role requis, garde anti-soi et
 * autorisation sur autrui. Aucun acces base ; le token est simule.
 */
final class UtilisateurVoterTest extends TestCase
{
    private const TOUS = [
        UtilisateurVoter::VOIR,
        UtilisateurVoter::MODIFIER,
        UtilisateurVoter::CHANGER_ROLE,
        UtilisateurVoter::ACTIVER,
        UtilisateurVoter::DESACTIVER,
    ];

    private UtilisateurVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new UtilisateurVoter();
    }

    private function utilisateur(int $id, Role $role): Utilisateur
    {
        $u = (new Utilisateur())->setRole($role);
        // L'id n'a pas de setter (genere par Doctrine) : on l'injecte par reflexion pour le test.
        (new \ReflectionProperty(Utilisateur::class, 'id'))->setValue($u, $id);

        return $u;
    }

    private function vote(Utilisateur $acteur, Utilisateur $cible, string $attribut): int
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($acteur);

        return $this->voter->vote($token, $cible, [$attribut]);
    }

    public function test_super_admin_autorise_toutes_les_actions_sur_autrui(): void
    {
        $admin = $this->utilisateur(1, Role::SUPER_ADMIN);
        $cible = $this->utilisateur(2, Role::EMPRUNTEUR);

        foreach (self::TOUS as $attribut) {
            self::assertSame(Voter::ACCESS_GRANTED, $this->vote($admin, $cible, $attribut), $attribut);
        }
    }

    public function test_garde_anti_soi_sur_role_et_activation(): void
    {
        $admin = $this->utilisateur(1, Role::SUPER_ADMIN);

        // Sur soi-meme : consultation et modification de l'identite restent permises...
        self::assertSame(Voter::ACCESS_GRANTED, $this->vote($admin, $admin, UtilisateurVoter::VOIR));
        self::assertSame(Voter::ACCESS_GRANTED, $this->vote($admin, $admin, UtilisateurVoter::MODIFIER));

        // ...mais changer son propre role ou (des)activer son propre compte est refuse (anti lock-out).
        self::assertSame(Voter::ACCESS_DENIED, $this->vote($admin, $admin, UtilisateurVoter::CHANGER_ROLE));
        self::assertSame(Voter::ACCESS_DENIED, $this->vote($admin, $admin, UtilisateurVoter::ACTIVER));
        self::assertSame(Voter::ACCESS_DENIED, $this->vote($admin, $admin, UtilisateurVoter::DESACTIVER));
    }

    public function test_refus_pour_un_non_super_admin(): void
    {
        $gestionnaire = $this->utilisateur(1, Role::GESTIONNAIRE);
        $cible = $this->utilisateur(2, Role::EMPRUNTEUR);

        foreach (self::TOUS as $attribut) {
            self::assertSame(Voter::ACCESS_DENIED, $this->vote($gestionnaire, $cible, $attribut), $attribut);
        }
    }
}
