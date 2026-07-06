<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Repository\PretRepository;
use App\Security\Voter\PretVoter;
use App\Service\PretService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AnnulationPretTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-annule';

    private EntityManagerInterface $em;
    private PretService $service;
    private PretVoter $voter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(PretService::class);
        $this->voter = static::getContainer()->get(PretVoter::class);
        $this->purger();
    }

    protected function tearDown(): void
    {
        $this->purger();
        parent::tearDown();
    }

    private function purger(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\Pret p WHERE EXISTS (SELECT e2.id FROM App\Entity\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'annule.%')->execute();
    }

    private function emprunteur(string $suffixe): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('annule.' . $suffixe . '.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('T')->setPrenom($suffixe)
            ->setRole(Role::EMPRUNTEUR)->setEstActif(true)->setMotDePasseHash('x');
        $this->em->persist($u);

        return $u;
    }

    private function pret(Utilisateur $emprunteur, StatutPret $statut = StatutPret::DEMANDE): Pret
    {
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $this->em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $this->em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $this->em->persist($ex);
        $pret = (new Pret())->setExemplaire($ex)->setEmprunteur($emprunteur)
            ->setDateDebut(new \DateTimeImmutable('2026-09-10 00:00:00'))
            ->setDateFin(new \DateTimeImmutable('2026-09-15 00:00:00'))
            ->setStatut($statut);
        $this->em->persist($pret);
        $this->em->flush();

        return $pret;
    }

    private function jeton(Utilisateur $u): UsernamePasswordToken
    {
        return new UsernamePasswordToken($u, 'main', $u->getRoles());
    }

    public function test_find_by_emprunteur_ne_retourne_que_ses_prets(): void
    {
        $moi = $this->emprunteur('moi');
        $autre = $this->emprunteur('autre');
        $this->pret($moi);
        $this->pret($moi);
        $this->pret($autre);
        $this->em->flush();

        $repo = static::getContainer()->get(PretRepository::class);
        $mesPrets = $repo->findByEmprunteur($moi);

        self::assertCount(2, $mesPrets);
        foreach ($mesPrets as $pret) {
            self::assertSame($moi->getId(), $pret->getEmprunteur()->getId());
        }
    }

    public function test_annuler_sa_demande_la_passe_en_annule(): void
    {
        $moi = $this->emprunteur('moi');
        $pret = $this->pret($moi);

        $this->service->annuler($pret, $moi);

        self::assertSame(StatutPret::ANNULE, $pret->getStatut());
    }

    public function test_annuler_le_pret_d_autrui_ne_fait_rien(): void
    {
        $moi = $this->emprunteur('moi');
        $autre = $this->emprunteur('autre');
        $pret = $this->pret($autre);

        $this->service->annuler($pret, $moi);

        self::assertSame(StatutPret::DEMANDE, $pret->getStatut());
    }

    public function test_annuler_un_pret_valide_ne_fait_rien(): void
    {
        $moi = $this->emprunteur('moi');
        $pret = $this->pret($moi, StatutPret::VALIDE);

        $this->service->annuler($pret, $moi);

        self::assertSame(StatutPret::VALIDE, $pret->getStatut());
    }

    public function test_voter_annuler_accorde_sur_sa_demande(): void
    {
        $moi = $this->emprunteur('moi');
        $pret = $this->pret($moi);

        $vote = $this->voter->vote($this->jeton($moi), $pret, [PretVoter::ANNULER]);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    public function test_voter_annuler_refuse_sur_pret_d_autrui(): void
    {
        $moi = $this->emprunteur('moi');
        $autre = $this->emprunteur('autre');
        $pret = $this->pret($autre);

        $vote = $this->voter->vote($this->jeton($moi), $pret, [PretVoter::ANNULER]);
        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    public function test_voter_annuler_refuse_sur_son_pret_valide(): void
    {
        $moi = $this->emprunteur('moi');
        $pret = $this->pret($moi, StatutPret::VALIDE);

        $vote = $this->voter->vote($this->jeton($moi), $pret, [PretVoter::ANNULER]);
        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }
}
