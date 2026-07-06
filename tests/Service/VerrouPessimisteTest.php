<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Enum\EtatExemplaire;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Prouve, de facon deterministe, que le verrou PESSIMISTIC_WRITE (SELECT ... FOR UPDATE) sur un
 * exemplaire est exclusif : tant qu'une transaction le detient, une autre connexion ne peut pas
 * le prendre. C'est le mecanisme sur lequel repose la garantie RG-1 (US-3.4). Ici on prouve
 * l'EXCLUSIVITE du verrou ; le test de concurrence reelle (invariant : jamais deux VALIDE)
 * viendra au bout suivant.
 */
final class VerrouPessimisteTest extends KernelTestCase
{
    private const MARQUEUR = 'zz-test-verrou';

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
        $this->em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
    }

    private function creerExemplaire(): int
    {
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $this->em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $this->em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-ex')
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $this->em->persist($ex);
        $this->em->flush();

        $id = $ex->getId();
        self::assertNotNull($id);

        return $id;
    }

    public function test_le_verrou_pessimiste_est_exclusif(): void
    {
        $idExemplaire = $this->creerExemplaire();

        $connexion1 = $this->em->getConnection();
        // Seconde connexion DISTINCTE vers la meme base (parametres de la connexion par defaut).
        $connexion2 = DriverManager::getConnection($connexion1->getParams());

        try {
            // La 2e connexion abandonnera vite si elle ne peut pas prendre le verrou.
            $connexion2->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

            // Connexion 1 : verrou exclusif sur la ligne de l'exemplaire, laisse la transaction ouverte.
            $connexion1->beginTransaction();
            $connexion1->executeQuery(
                'SELECT id FROM exemplaire WHERE id = ? FOR UPDATE',
                [$idExemplaire],
            );

            // Connexion 2 : meme ligne -> doit etre bloquee puis lever un timeout de verrou.
            $connexion2->beginTransaction();
            try {
                $connexion2->executeQuery(
                    'SELECT id FROM exemplaire WHERE id = ? FOR UPDATE',
                    [$idExemplaire],
                );
                self::fail('La seconde connexion aurait du etre bloquee par le verrou pessimiste.');
            } catch (LockWaitTimeoutException) {
                // Comportement attendu : le verrou de la connexion 1 est exclusif.
                self::addToAssertionCount(1);
            } finally {
                if ($connexion2->isTransactionActive()) {
                    $connexion2->rollBack();
                }
            }
        } finally {
            // Relache le verrou AVANT la purge du tearDown.
            if ($connexion1->isTransactionActive()) {
                $connexion1->rollBack();
            }
            $connexion2->close();
        }
    }
}
