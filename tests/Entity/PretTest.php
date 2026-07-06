<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PretTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-pret';

    private function purger(EntityManagerInterface $em): void
    {
        // Ordre impose par les FK : prets -> exemplaires -> materiels -> categories -> utilisateurs.
        $em->createQuery('DELETE FROM App\Entity\Pret p WHERE EXISTS (SELECT e2.id FROM App\Entity\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'pret.test.%')->execute();
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purger($em);
        parent::tearDown();
    }

    public function test_le_constructeur_initialise_une_demande(): void
    {
        $pret = new Pret();

        // A la creation : statut DEMANDE + date de demande horodatee (jamais dans le futur).
        self::assertSame(StatutPret::DEMANDE, $pret->getStatut());
        self::assertLessThanOrEqual(new \DateTimeImmutable(), $pret->getDateDemande());
        self::assertNull($pret->getValidateur());
        self::assertNull($pret->getDateValidation());
    }

    public function test_persistance_et_rehydratation_d_un_pret(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purger($em);

        // Graphe minimal : categorie -> materiel -> exemplaire + emprunteur.
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-ex')
            ->setEtat(EtatExemplaire::DISPONIBLE)
            ->setMateriel($mat);
        $em->persist($ex);
        $emprunteur = (new Utilisateur())
            ->setEmail('pret.test.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('Test')->setPrenom('Pret')
            ->setRole(Role::EMPRUNTEUR)->setEstActif(true)
            ->setMotDePasseHash('x');
        $em->persist($emprunteur);

        $pret = (new Pret())
            ->setExemplaire($ex)
            ->setEmprunteur($emprunteur)
            ->setDateDebut(new \DateTimeImmutable('2026-09-01 08:00:00'))
            ->setDateFin(new \DateTimeImmutable('2026-09-05 18:00:00'));
        $em->persist($pret);
        $em->flush();
        $id = $pret->getId();

        // Relecture depuis la base (vide le cache d'identite).
        $em->clear();
        $relu = $em->getRepository(Pret::class)->find($id);

        self::assertInstanceOf(Pret::class, $relu);
        // Le mapping enumType fait l'aller-retour VARCHAR <-> enum PHP.
        self::assertSame(StatutPret::DEMANDE, $relu->getStatut());
        self::assertSame('2026-09-01 08:00:00', $relu->getDateDebut()->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-05 18:00:00', $relu->getDateFin()->format('Y-m-d H:i:s'));
        // Associations rechargees depuis la base.
        self::assertSame(self::MARQUEUR . '-ex', $relu->getExemplaire()->getNumeroInventaire());
        self::assertStringStartsWith('pret.test.', $relu->getEmprunteur()->getEmail());
        self::assertNull($relu->getValidateur());
    }

    public function test_les_transitions_du_cycle_de_vie(): void
    {
        // Transition VALIDATION : DEMANDE -> VALIDE, validateur + date_validation renseignes.
        $valideur = (new Utilisateur())
            ->setEmail('pret.test.valideur@cnam-reunion.fr')
            ->setNom('Gest')->setPrenom('Valideur')
            ->setRole(Role::GESTIONNAIRE)->setEstActif(true)
            ->setMotDePasseHash('x');
        $dateValidation = new \DateTimeImmutable('2026-09-01 09:00:00');

        $pret = new Pret();
        $pret->setStatut(StatutPret::VALIDE)
            ->setValidateur($valideur)
            ->setDateValidation($dateValidation);

        self::assertSame(StatutPret::VALIDE, $pret->getStatut());
        self::assertSame($valideur, $pret->getValidateur());
        self::assertSame($dateValidation, $pret->getDateValidation());

        // Transition REFUS : statut REFUSE + motif renseigne.
        $refuse = (new Pret())
            ->setStatut(StatutPret::REFUSE)
            ->setMotifRefus('Exemplaire indisponible sur la periode');
        self::assertSame(StatutPret::REFUSE, $refuse->getStatut());
        self::assertSame('Exemplaire indisponible sur la periode', $refuse->getMotifRefus());

        // Transition RETOUR : statut RETOURNE + date_retour renseignee.
        $dateRetour = new \DateTimeImmutable('2026-09-06 10:00:00');
        $retour = (new Pret())
            ->setStatut(StatutPret::RETOURNE)
            ->setDateRetour($dateRetour);
        self::assertSame(StatutPret::RETOURNE, $retour->getStatut());
        self::assertSame($dateRetour, $retour->getDateRetour());
    }
}
