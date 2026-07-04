<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Utilisateur;
use App\Enum\Role;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

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

    public function test_accesseurs_et_valeurs_par_defaut(): void
    {
        $u = new Utilisateur();

        // valeurs par defaut
        self::assertTrue($u->isEstActif());
        self::assertTrue($u->isEmailRappel());
        self::assertSame(Role::EMPRUNTEUR, $u->getRole());
        self::assertLessThanOrEqual(new \DateTimeImmutable(), $u->getDateCreation());
        self::assertNull($u->getId());

        // cycle setters -> getters
        $u->setEmail('gestionnaire@cnam-reunion.fr')
            ->setNom('Payet')
            ->setPrenom('Marie')
            ->setMotDePasseHash('$argon2id$fake')
            ->setRole(Role::GESTIONNAIRE)
            ->setEstActif(false)
            ->setEmailRappel(false);

        self::assertSame('gestionnaire@cnam-reunion.fr', $u->getEmail());
        self::assertSame('Payet', $u->getNom());
        self::assertSame('Marie', $u->getPrenom());
        self::assertSame('$argon2id$fake', $u->getPassword());
        self::assertSame(Role::GESTIONNAIRE, $u->getRole());
        self::assertFalse($u->isEstActif());
        self::assertFalse($u->isEmailRappel());
    }

    public function test_erase_credentials_ne_leve_rien(): void
    {
        $u = new Utilisateur();
        $u->eraseCredentials();
        $this->addToAssertionCount(1);
    }

    public function test_get_user_identifier_leve_une_exception_si_email_vide(): void
    {
        // Email explicitement vide : declenche la garde (defense en profondeur).
        // NB : sans setEmail(), la propriete typee serait non initialisee et
        // leverait \Error, pas la \LogicException metier visee ici.
        $u = (new Utilisateur())->setEmail('');

        $this->expectException(\LogicException::class);
        $u->getUserIdentifier();
    }

    public function test_is_equal_to_refuse_un_autre_type_dutilisateur(): void
    {
        $u = (new Utilisateur())->setEmail('a@b.re')->setRole(Role::EMPRUNTEUR);

        $autre = new class implements UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'a@b.re';
            }
        };

        self::assertFalse($u->isEqualTo($autre), 'isEqualTo doit refuser un utilisateur d\'un autre type.');
    }
}
