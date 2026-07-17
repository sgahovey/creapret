<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JournalAdmin;
use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Enum\TypeActionJournal;
use App\Service\JournalAdminService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class JournalAdminServiceTest extends TestCase
{
    private MockObject&EntityManagerInterface $em;
    private JournalAdminService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new JournalAdminService($this->em);
    }

    private function utilisateur(int $id, string $prenom, string $nom): Utilisateur
    {
        $u = (new Utilisateur())->setPrenom($prenom)->setNom($nom)->setEmail('x')->setRole(Role::GESTIONNAIRE)->setEstActif(true);
        $ref = new \ReflectionProperty(Utilisateur::class, 'id');
        $ref->setValue($u, $id);

        return $u;
    }

    public function test_enregistrer_persiste_une_trace_figee_sans_flush(): void
    {
        $acteur = $this->utilisateur(10, 'Gerard', 'Gestion');
        $cible = $this->utilisateur(20, 'Marie', 'Dupont');

        // Contrat persist-only : persist() appele une fois, flush() jamais (laisse a l'appelant).
        $this->em->expects(self::once())->method('persist')->with(self::callback(
            static function (JournalAdmin $entree) {
                return TypeActionJournal::PRET_VALIDATION === $entree->getTypeAction()
                    && 10 === $entree->getActeurId()
                    && 'Gerard Gestion' === $entree->getActeurLibelle()
                    && 20 === $entree->getCibleId()
                    && 'Marie Dupont' === $entree->getCibleLibelle();
            },
        ));
        $this->em->expects(self::never())->method('flush');

        $this->service->enregistrer(TypeActionJournal::PRET_VALIDATION, $acteur, $cible);
    }

    public function test_enregistrer_accepte_une_cible_nulle_et_des_details(): void
    {
        $acteur = $this->utilisateur(10, 'Gerard', 'Gestion');

        $this->em->expects(self::once())->method('persist')->with(self::callback(
            static function (JournalAdmin $entree) {
                return null === $entree->getCibleId()
                    && null === $entree->getCibleLibelle()
                    && 'Materiel indisponible' === $entree->getDetails();
            },
        ));

        $this->service->enregistrer(TypeActionJournal::PRET_REFUS, $acteur, null, 'Materiel indisponible');
    }
}
