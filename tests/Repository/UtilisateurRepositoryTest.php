<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UtilisateurRepositoryTest extends KernelTestCase
{
    private const MARQUEUR = 'zzUrepo';

    private EntityManagerInterface $em;
    private UtilisateurRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repo = self::getContainer()->get(UtilisateurRepository::class);
        $this->purger();
    }

    protected function tearDown(): void
    {
        $this->purger();
        parent::tearDown();
    }

    private function purger(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'urepo.%')->execute();
    }

    private function creer(string $nom, string $prenom, Role $role, bool $actif): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('urepo.' . uniqid() . '@cnam-reunion.fr')
            ->setNom($nom)->setPrenom($prenom)
            ->setRole($role)->setEstActif($actif)
            ->setMotDePasseHash('x');
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    public function test_find_all_pour_admin_filtre_en_sql_et_trie(): void
    {
        // Nom marqueur unique -> la recherche ne remonte que ces comptes.
        $this->creer(self::MARQUEUR . 'Zulu', 'Alice', Role::EMPRUNTEUR, true);
        $this->creer(self::MARQUEUR . 'Alpha', 'Bob', Role::GESTIONNAIRE, true);

        $page = $this->repo->findAllPourAdmin(1, 20, self::MARQUEUR);
        $resultats = iterator_to_array($page, false);

        self::assertCount(2, $page);          // total via Paginator (Countable)
        self::assertCount(2, $resultats);
        // Tri par nom ASC : ...Alpha avant ...Zulu.
        self::assertSame(self::MARQUEUR . 'Alpha', $resultats[0]->getNom());
        self::assertSame(self::MARQUEUR . 'Zulu', $resultats[1]->getNom());
    }

    public function test_find_all_pour_admin_recherche_par_prenom(): void
    {
        $this->creer(self::MARQUEUR . 'Un', 'Prenommarqueurxyz', Role::EMPRUNTEUR, true);
        $this->creer(self::MARQUEUR . 'Deux', 'Autre', Role::EMPRUNTEUR, true);

        $page = $this->repo->findAllPourAdmin(1, 20, 'Prenommarqueurxyz');
        $resultats = iterator_to_array($page, false);

        self::assertCount(1, $page);
        self::assertSame('Prenommarqueurxyz', $resultats[0]->getPrenom());
    }

    public function test_count_super_admins_actifs_ne_compte_que_les_actifs(): void
    {
        $baseline = $this->repo->countSuperAdminsActifs();

        $this->creer(self::MARQUEUR . 'SaActif1', 'X', Role::SUPER_ADMIN, true);
        $this->creer(self::MARQUEUR . 'SaActif2', 'Y', Role::SUPER_ADMIN, true);
        $this->creer(self::MARQUEUR . 'SaInactif', 'Z', Role::SUPER_ADMIN, false);

        // Seuls les deux actifs s'ajoutent au socle existant.
        self::assertSame($baseline + 2, $this->repo->countSuperAdminsActifs());
    }
}
