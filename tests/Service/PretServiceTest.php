<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\ResultatValidation;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Repository\PretRepository;
use App\Service\PretService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PretServiceTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-validation';

    private EntityManagerInterface $em;
    private PretService $service;
    private Materiel $materiel;
    private Utilisateur $emprunteur;
    private Utilisateur $gestionnaire;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        // Instanciation directe : le service n'est pas encore consomme par un controleur (US-3.4 B),
        // donc le conteneur DI le supprime comme inutilise ; on l'assemble avec ses dependances.
        $this->service = new PretService($this->em, static::getContainer()->get(PretRepository::class));
        $this->purger();

        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $this->em->persist($cat);
        $this->materiel = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $this->em->persist($this->materiel);
        $this->emprunteur = $this->utilisateur('emprunteur', Role::EMPRUNTEUR);
        $this->gestionnaire = $this->utilisateur('gestionnaire', Role::GESTIONNAIRE);
        $this->em->flush();
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
            ->setParameter('p', 'validation.%')->execute();
    }

    private function utilisateur(string $suffixe, Role $role): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('validation.' . $suffixe . '.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('T')->setPrenom($suffixe)
            ->setRole($role)->setEstActif(true)
            ->setMotDePasseHash('x');
        $this->em->persist($u);

        return $u;
    }

    private function exemplaire(string $suffixe): Exemplaire
    {
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-' . $suffixe)
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($this->materiel);
        $this->em->persist($ex);
        $this->em->flush();

        return $ex;
    }

    private function pret(Exemplaire $ex, string $debut, string $fin, StatutPret $statut = StatutPret::DEMANDE): Pret
    {
        $pret = (new Pret())
            ->setExemplaire($ex)->setEmprunteur($this->emprunteur)
            ->setDateDebut(new \DateTimeImmutable($debut))
            ->setDateFin(new \DateTimeImmutable($fin))
            ->setStatut($statut);
        $this->em->persist($pret);
        $this->em->flush();

        return $pret;
    }

    public function test_validation_nominale_passe_le_pret_en_valide(): void
    {
        $ex = $this->exemplaire('a');
        $pret = $this->pret($ex, '2026-09-10 00:00:00', '2026-09-15 00:00:00');

        $resultat = $this->service->valider($pret, $this->gestionnaire);

        self::assertSame(ResultatValidation::VALIDE, $resultat);
        self::assertSame(StatutPret::VALIDE, $pret->getStatut());
        self::assertNotNull($pret->getDateValidation());
        self::assertSame($this->gestionnaire->getId(), $pret->getValidateur()?->getId());
    }

    public function test_validation_en_conflit_refuse_le_pret(): void
    {
        $ex = $this->exemplaire('a');
        // Un pret VALIDE occupe deja l'exemplaire sur une periode chevauchante.
        $this->pret($ex, '2026-09-08 00:00:00', '2026-09-20 00:00:00', StatutPret::VALIDE);
        // Une demande sur une periode qui chevauche.
        $demande = $this->pret($ex, '2026-09-10 00:00:00', '2026-09-15 00:00:00');

        $resultat = $this->service->valider($demande, $this->gestionnaire);

        self::assertSame(ResultatValidation::CONFLIT, $resultat);
        self::assertSame(StatutPret::REFUSE, $demande->getStatut());
        self::assertNotNull($demande->getMotifRefus());
    }

    public function test_validation_d_un_pret_deja_traite_ne_fait_rien(): void
    {
        $ex = $this->exemplaire('a');
        // Le pret est deja VALIDE (traite par ailleurs).
        $pret = $this->pret($ex, '2026-09-10 00:00:00', '2026-09-15 00:00:00', StatutPret::VALIDE);

        $resultat = $this->service->valider($pret, $this->gestionnaire);

        self::assertSame(ResultatValidation::DEJA_TRAITE, $resultat);
        self::assertSame(StatutPret::VALIDE, $pret->getStatut());
    }

    public function test_refus_explicite_avec_motif(): void
    {
        $ex = $this->exemplaire('a');
        $pret = $this->pret($ex, '2026-09-10 00:00:00', '2026-09-15 00:00:00');

        $this->service->refuser($pret, $this->gestionnaire, 'Materiel reserve pour une formation.');

        self::assertSame(StatutPret::REFUSE, $pret->getStatut());
        self::assertSame('Materiel reserve pour une formation.', $pret->getMotifRefus());
        self::assertSame($this->gestionnaire->getId(), $pret->getValidateur()?->getId());
    }
}
