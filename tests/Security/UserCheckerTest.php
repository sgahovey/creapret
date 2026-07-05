<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Utilisateur;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\DisabledException;

final class UserCheckerTest extends TestCase
{
    public function test_un_compte_actif_passe_la_verification(): void
    {
        $checker = new UserChecker();
        $u = (new Utilisateur())->setEmail('actif@cnam-reunion.fr')->setEstActif(true);

        $checker->checkPreAuth($u);
        $this->addToAssertionCount(1); // aucune exception levee
    }

    public function test_un_compte_desactive_est_refuse(): void
    {
        $checker = new UserChecker();
        $u = (new Utilisateur())->setEmail('inactif@cnam-reunion.fr')->setEstActif(false);

        $this->expectException(DisabledException::class);
        $checker->checkPreAuth($u);
    }
}
