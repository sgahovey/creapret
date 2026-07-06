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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test de concurrence REELLE (US-3.4, RG-1). Deux process independants valident deux demandes
 * differentes sur le MEME exemplaire et des periodes chevauchantes, lances pour demarrer
 * quasi simultanement. L'assertion porte sur l'INVARIANT (au plus un pret VALIDE), pas sur qui
 * gagne : elle est donc deterministe quel que soit l'ordre reel d'execution (non flaky).
 */
final class ConcurrenceValidationTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-concurrence';

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
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
            ->setParameter('p', 'concurrence.%')->execute();
    }

    /**
     * @return array{resource, array<int, resource>}
     */
    private function lancerWorker(int $idPret, int $idValidateur, float $depart): array
    {
        $descripteurs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        // Herite de l'environnement reel (dont DATABASE_URL fourni par le conteneur, avec le vrai
        // mot de passe) et force l'env de test : .env seul contiendrait un placeholder inutilisable.
        $env = getenv();
        $env['APP_ENV'] = 'test';
        $cmd = ['php', dirname(__DIR__, 2) . '/bin/valider-pret-concurrent.php', (string) $idPret, (string) $idValidateur, (string) $depart];

        $process = proc_open($cmd, $descripteurs, $pipes, dirname(__DIR__, 2), $env);
        self::assertIsResource($process);

        return [$process, $pipes];
    }

    public function test_deux_validations_concurrentes_ne_creent_jamais_deux_valide(): void
    {
        // Graphe : un exemplaire, deux demandes DEMANDE sur des periodes qui se chevauchent.
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $this->em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $this->em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-ex')
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $this->em->persist($ex);

        $emprunteur = (new Utilisateur())
            ->setEmail('concurrence.emp.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('E')->setPrenom('mp')->setRole(Role::EMPRUNTEUR)->setEstActif(true)->setMotDePasseHash('x');
        $this->em->persist($emprunteur);
        $gestionnaire = (new Utilisateur())
            ->setEmail('concurrence.gest.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('G')->setPrenom('est')->setRole(Role::GESTIONNAIRE)->setEstActif(true)->setMotDePasseHash('x');
        $this->em->persist($gestionnaire);

        $pretA = (new Pret())->setExemplaire($ex)->setEmprunteur($emprunteur)
            ->setDateDebut(new \DateTimeImmutable('2026-09-10 00:00:00'))
            ->setDateFin(new \DateTimeImmutable('2026-09-20 00:00:00'));
        $this->em->persist($pretA);
        $pretB = (new Pret())->setExemplaire($ex)->setEmprunteur($emprunteur)
            ->setDateDebut(new \DateTimeImmutable('2026-09-15 00:00:00'))
            ->setDateFin(new \DateTimeImmutable('2026-09-25 00:00:00'));
        $this->em->persist($pretB);

        $this->em->flush();

        $idA = $pretA->getId();
        $idB = $pretB->getId();
        $idG = $gestionnaire->getId();
        self::assertNotNull($idA);
        self::assertNotNull($idB);
        self::assertNotNull($idG);

        $idEx = $ex->getId();

        // Top-depart commun dans 1 seconde : les deux workers se disputeront le verrou.
        $depart = microtime(true) + 1.0;
        [$procA, $pipesA] = $this->lancerWorker($idA, $idG, $depart);
        [$procB, $pipesB] = $this->lancerWorker($idB, $idG, $depart);

        $sortieA = (string) stream_get_contents($pipesA[1]);
        $sortieB = (string) stream_get_contents($pipesB[1]);
        foreach ([$pipesA, $pipesB] as $pipes) {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
        }
        proc_close($procA);
        proc_close($procB);

        // Garde-fou : les deux workers ont reellement tourne et TROUVE leur pret (sinon
        // l'invariant serait trivialement satisfait par 0 validation).
        foreach (['A' => $sortieA, 'B' => $sortieB] as $nom => $sortie) {
            self::assertNotSame('', $sortie, "Le worker {$nom} n'a rien renvoye.");
            self::assertNotSame('INTROUVABLE', $sortie, "Le worker {$nom} n'a pas trouve son pret (mauvaise base ?).");
        }

        // Invariant RG-1 : exactement UN pret VALIDE sur cet exemplaire (un worker gagne le
        // verrou et valide, l'autre reverifie sous verrou et tombe en CONFLIT). Jamais deux.
        $valides = (int) $this->em->createQuery('SELECT COUNT(p.id) FROM App\Entity\Pret p WHERE p.exemplaire = :ex AND p.statut = :v')
            ->setParameter('ex', $idEx)
            ->setParameter('v', StatutPret::VALIDE->value)
            ->getSingleScalarResult();

        self::assertSame(1, $valides, sprintf(
            'RG-1 : attendu 1 pret VALIDE, obtenu %d (worker A: %s, worker B: %s).',
            $valides,
            $sortieA,
            $sortieB,
        ));
    }
}
