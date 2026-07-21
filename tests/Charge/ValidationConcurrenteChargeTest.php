<?php

declare(strict_types=1);

namespace App\Tests\Charge;

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
 * Test de CHARGE sur le verrou pessimiste (RG-1) — generalisation de
 * tests/Service/ConcurrenceValidationTest.php de 2 a N processus.
 *
 * N processus systeme independants tentent de valider, au MEME instant, N demandes distinctes
 * portant sur LE MEME exemplaire avec des periodes qui se chevauchent toutes. La contention est
 * donc maximale : un seul peut gagner le verrou, les autres doivent tomber en CONFLIT.
 *
 * L'assertion porte sur l'INVARIANT (exactement un pret VALIDE), jamais sur l'identite du
 * processus gagnant : celle-ci est indeterministe et rendrait le test instable. Le test est donc
 * deterministe quel que soit l'ordre reel d'acquisition du verrou.
 *
 * Les durees mesurees documentent le comportement sous contention ; elles ne font l'objet
 * d'AUCUNE assertion (un seuil de duree serait dependant de la machine, donc instable).
 */
final class ValidationConcurrenteChargeTest extends KernelTestCase
{
    /**
     * Nombre de processus concurrents. Parametrable : augmenter pour durcir la contention.
     * A 10, la charge reste supportable par un poste de developpement.
     */
    private const int PROCESSUS_CONCURRENTS = 10;

    /** Delai avant le top-depart commun, laissant aux N noyaux le temps de booter. */
    private const float DELAI_TOP_DEPART_SECONDES = 2.0;

    private const string MARQUEUR = 'zz-test-charge';

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

    /**
     * Nettoyage complet des donnees jetables. Les workers COMMITTENT reellement (processus
     * distincts) : l'annulation par transaction est impossible, on purge donc par marqueur.
     */
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
            ->setParameter('p', 'charge.%')->execute();
    }

    /**
     * Lance un worker autonome en processus separe, tuyaux en mode NON bloquant afin que le
     * parent puisse dater la fin de chacun sans se bloquer sur la lecture du premier.
     *
     * @return array{resource, array<int, resource>}
     */
    private function lancerWorker(int $idPret, int $idValidateur, float $depart): array
    {
        $descripteurs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        // Herite de l'environnement reel (dont DATABASE_URL fourni par le conteneur) et force
        // l'env de test : .env seul contiendrait un placeholder inutilisable.
        $env = getenv();
        $env['APP_ENV'] = 'test';
        $cmd = ['php', dirname(__DIR__, 2) . '/bin/valider-pret-concurrent.php', (string) $idPret, (string) $idValidateur, (string) $depart];

        $process = proc_open($cmd, $descripteurs, $pipes, dirname(__DIR__, 2), $env);
        self::assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return [$process, $pipes];
    }

    public function test_dix_validations_concurrentes_ne_creent_jamais_deux_valide(): void
    {
        $n = self::PROCESSUS_CONCURRENTS;

        // --- Graphe : UN exemplaire, N demandes dont les periodes se chevauchent toutes. ---
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $this->em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $this->em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-ex')
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $this->em->persist($ex);

        $gestionnaire = (new Utilisateur())
            ->setEmail('charge.gest.' . uniqid() . '@cnam-reunion.fr')
            ->setNom('G')->setPrenom('est')->setRole(Role::GESTIONNAIRE)->setEstActif(true)->setMotDePasseHash('x');
        $this->em->persist($gestionnaire);

        /** @var list<Pret> $prets */
        $prets = [];
        for ($i = 0; $i < $n; ++$i) {
            $emprunteur = (new Utilisateur())
                ->setEmail('charge.emp' . $i . '.' . uniqid() . '@cnam-reunion.fr')
                ->setNom('E' . $i)->setPrenom('mp')->setRole(Role::EMPRUNTEUR)->setEstActif(true)->setMotDePasseHash('x');
            $this->em->persist($emprunteur);

            // Debuts echelonnes mais fin commune tardive : toutes les periodes se recouvrent sur
            // la fenetre [dernier debut ; fin commune], donc elles se chevauchent deux a deux.
            $pret = (new Pret())->setExemplaire($ex)->setEmprunteur($emprunteur)
                ->setDateDebut((new \DateTimeImmutable('2026-09-10 00:00:00'))->modify(sprintf('+%d days', $i)))
                ->setDateFin(new \DateTimeImmutable('2026-10-20 00:00:00'));
            $this->em->persist($pret);
            $prets[] = $pret;
        }

        $this->em->flush();

        $idGestionnaire = $gestionnaire->getId();
        self::assertNotNull($idGestionnaire);
        $idExemplaire = $ex->getId();

        // --- Lancement : top-depart commun, contention maximale sur le verrou. ---
        $debutLancement = microtime(true);
        $depart = $debutLancement + self::DELAI_TOP_DEPART_SECONDES;

        /** @var array<int, resource> $processus */
        $processus = [];
        /** @var array<int, array<int, resource>> $tuyaux */
        $tuyaux = [];
        foreach ($prets as $i => $pret) {
            $idPret = $pret->getId();
            self::assertNotNull($idPret);
            [$processus[$i], $tuyaux[$i]] = $this->lancerWorker($idPret, $idGestionnaire, $depart);
        }

        // --- Collecte : scrutation non bloquante pour dater la fin de CHAQUE worker. ---
        $sorties = array_fill(0, $n, '');
        /** @var array<int, float|null> $fins */
        $fins = array_fill(0, $n, null);
        $restants = range(0, $n - 1);

        while ($restants !== []) {
            foreach ($restants as $cle => $i) {
                $sorties[$i] .= (string) stream_get_contents($tuyaux[$i][1]);
                $etat = proc_get_status($processus[$i]);
                if (false === $etat['running']) {
                    $sorties[$i] .= (string) stream_get_contents($tuyaux[$i][1]);
                    $fins[$i] = microtime(true);
                    unset($restants[$cle]);
                }
            }
            usleep(2000);
        }

        foreach ($tuyaux as $paire) {
            foreach ($paire as $tuyau) {
                fclose($tuyau);
            }
        }
        foreach ($processus as $proc) {
            proc_close($proc);
        }
        $dureeTotale = microtime(true) - $debutLancement;

        // --- Garde-fou anti-faux-positif (repris de ConcurrenceValidationTest) ---
        // Si les N processus echouaient a demarrer, AUCUN pret ne serait valide et l'invariant
        // « pas plus d'un VALIDE » serait satisfait TRIVIALEMENT, sans rien avoir prouve.
        foreach ($sorties as $i => $sortie) {
            self::assertNotSame('', $sortie, "Le worker {$i} n'a rien renvoye (processus non demarre ?).");
            self::assertNotSame('INTROUVABLE', $sortie, "Le worker {$i} n'a pas trouve son pret (mauvaise base ?).");
        }

        // --- Mesures : documentent le comportement sous contention, sans assertion de seuil. ---
        $durees = [];
        foreach ($fins as $fin) {
            if (null !== $fin) {
                $durees[] = $fin - $depart;
            }
        }
        $plusLente = $durees !== [] ? max($durees) : 0.0;

        fwrite(STDERR, sprintf(
            "\n[CHARGE RG-1] %d processus concurrents sur 1 exemplaire\n"
            . "  duree totale (lancement -> dernier worker) : %.3f s\n"
            . "  validation la plus lente (apres top-depart) : %.3f s\n"
            . "  issues des workers : %s\n",
            $n,
            $dureeTotale,
            $plusLente,
            implode(', ', array_map(
                static fn (string $cle, int $nb): string => $cle . '=' . $nb,
                array_keys(array_count_values($sorties)),
                array_values(array_count_values($sorties)),
            )),
        ));

        // --- Invariant RG-1 : exactement UN pret VALIDE, les N-1 autres REFUSE. ---
        $valides = (int) $this->em->createQuery('SELECT COUNT(p.id) FROM App\Entity\Pret p WHERE p.exemplaire = :ex AND p.statut = :s')
            ->setParameter('ex', $idExemplaire)
            ->setParameter('s', StatutPret::VALIDE->value)
            ->getSingleScalarResult();

        $refuses = (int) $this->em->createQuery('SELECT COUNT(p.id) FROM App\Entity\Pret p WHERE p.exemplaire = :ex AND p.statut = :s')
            ->setParameter('ex', $idExemplaire)
            ->setParameter('s', StatutPret::REFUSE->value)
            ->getSingleScalarResult();

        $detail = sprintf('(issues workers : %s)', implode(' | ', $sorties));

        self::assertSame(1, $valides, sprintf(
            'RG-1 sous charge : attendu exactement 1 pret VALIDE sur %d validations concurrentes, obtenu %d. %s',
            $n,
            $valides,
            $detail,
        ));

        self::assertSame($n - 1, $refuses, sprintf(
            'RG-1 sous charge : attendu %d prets REFUSE (conflit), obtenu %d. %s',
            $n - 1,
            $refuses,
            $detail,
        ));
    }
}
