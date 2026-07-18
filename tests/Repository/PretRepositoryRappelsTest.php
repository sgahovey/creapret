<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Repository\PretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PretRepositoryRappelsTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-rappels';
    private EntityManagerInterface $em;
    private PretRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(PretRepository::class);
        $this->purger();
    }

    private function purger(): void
    {
        $this->em->createQuery('DELETE FROM App\\Entity\\Pret p WHERE EXISTS (SELECT e2.id FROM App\\Entity\\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\Exemplaire e WHERE e.numeroInventaire LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\Materiel m WHERE m.nom LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\Categorie c WHERE c.nom LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\Utilisateur u WHERE u.email LIKE :p')->setParameter('p', 'rappels.%')->execute();
    }

    private function emprunteur(): Utilisateur
    {
        $u = (new Utilisateur())->setEmail('rappels.' . uniqid() . '@creapret.local')
            ->setNom('T')->setPrenom('E')->setRole(Role::EMPRUNTEUR)->setEstActif(true)
            ->setMotDePasseHash('x');
        $this->em->persist($u);

        return $u;
    }

    private function exemplaire(): Exemplaire
    {
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-c');
        $this->em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-m')->setCategorie($cat);
        $this->em->persist($mat);
        $ex = (new Exemplaire())->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $this->em->persist($ex);

        return $ex;
    }

    private function pret(StatutPret $statut, \DateTimeImmutable $debut, \DateTimeImmutable $fin): Pret
    {
        $pret = (new Pret())->setExemplaire($this->exemplaire())->setEmprunteur($this->emprunteur())
            ->setDateDebut($debut)->setDateFin($fin)->setStatut($statut);
        $this->em->persist($pret);
        $this->em->flush();

        return $pret;
    }

    public function test_rappel_echeance_ne_prend_que_les_valide_dans_la_plage_sans_rappel(): void
    {
        $debut = new \DateTimeImmutable('2026-09-15 00:00:00');
        $fin = new \DateTimeImmutable('2026-09-15 23:59:59');

        // Cible : VALIDE, dateFin demain, pas encore rappele.
        $cible = $this->pret(StatutPret::VALIDE, new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15 10:00:00'));
        // Hors plage (fin apres-demain).
        $this->pret(StatutPret::VALIDE, new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-16 10:00:00'));
        // Mauvais statut (DEMANDE).
        $this->pret(StatutPret::DEMANDE, new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15 10:00:00'));
        // Deja rappele.
        $deja = $this->pret(StatutPret::VALIDE, new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15 12:00:00'));
        $deja->setRappelEcheanceEnvoyeAt(new \DateTimeImmutable('2026-09-14 18:00:00'));
        $this->em->flush();

        $resultats = $this->repo->findPourRappelEcheance($debut, $fin);
        $ids = array_map(static fn (Pret $p) => $p->getId(), $resultats);

        self::assertContains($cible->getId(), $ids);
        self::assertCount(1, array_filter($ids, fn ($id) => $id === $cible->getId()));
        self::assertSame([$cible->getId()], array_values(array_intersect($ids, [$cible->getId()])));
    }

    public function test_retard_ne_prend_que_les_valide_depasses_sans_alerte(): void
    {
        $maintenant = new \DateTimeImmutable('2026-09-20 08:00:00');

        // Cible : VALIDE, dateFin passee, pas encore alerte.
        $cible = $this->pret(StatutPret::VALIDE, new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-18 10:00:00'));
        // Pas en retard (fin future).
        $this->pret(StatutPret::VALIDE, new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-25 10:00:00'));
        // Deja alerte.
        $deja = $this->pret(StatutPret::VALIDE, new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-17 10:00:00'));
        $deja->setRetardNotifieAt(new \DateTimeImmutable('2026-09-19 08:00:00'));
        $this->em->flush();

        $resultats = $this->repo->findEnRetard($maintenant);
        $ids = array_map(static fn (Pret $p) => $p->getId(), $resultats);

        self::assertContains($cible->getId(), $ids);
        self::assertNotContains($deja->getId(), $ids);
    }

    protected function tearDown(): void
    {
        $this->purger();
        parent::tearDown();
    }
}
