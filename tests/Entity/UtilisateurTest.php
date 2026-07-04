<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Utilisateur;
use App\Enum\Role;
use PHPUnit\Framework\TestCase;

final class UtilisateurTest extends TestCase
{
    public function test_get_roles_derive_le_prefixe_symfony(): void
    {
        $u = (new Utilisateur())->setRole(Role::GESTIONNAIRE);

        self::assertContains('ROLE_GESTIONNAIRE', $u->getRoles());
        self::assertContains('ROLE_USER', $u->getRoles());
    }

    public function test_user_identifier_est_l_email(): void
    {
        $u = (new Utilisateur())->setEmail('agent@cnam-reunion.fr');

        self::assertSame('agent@cnam-reunion.fr', $u->getUserIdentifier());
    }

    public function test_is_equal_to_detecte_la_desactivation(): void
    {
        $actif = (new Utilisateur())->setEmail('a@b.re')->setRole(Role::EMPRUNTEUR)->setEstActif(true);
        $inactif = (new Utilisateur())->setEmail('a@b.re')->setRole(Role::EMPRUNTEUR)->setEstActif(false);

        self::assertFalse($actif->isEqualTo($inactif), 'Un compte desactive ne doit plus etre equivalent.');
    }

    public function test_nom_complet(): void
    {
        $u = (new Utilisateur())->setNom('Hovey')->setPrenom('Saint-George');

        self::assertSame('Saint-George Hovey', $u->getNomComplet());
    }
}
