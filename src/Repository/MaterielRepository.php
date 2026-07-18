<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Materiel;
use App\Enum\EtatExemplaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Materiel>
 */
class MaterielRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Materiel::class);
    }

    /**
     * Etat du parc : pour chaque materiel, le nombre d'exemplaires par etat.
     *
     * Requete d'agregation (GROUP BY materiel + etat, COUNT) avec LEFT JOIN pour
     * inclure les materiels sans aucun exemplaire (comptes a zero). Le resultat plat
     * est recompose en PHP en un tableau structure exploitable par la vue. Prepare RG-4.
     *
     * @return list<array{materiel: Materiel, etats: array<string, int>, total: int}>
     */
    public function etatDuParc(): array
    {
        // Comptage en scalaire pur : une ligne par (materiel, etat). getScalarResult()
        // evite la deduplication du root entity que provoquerait l'hydratation objet sur
        // une requete a plusieurs lignes par materiel (GROUP BY sur un champ joint).
        /** @var list<array{materielId: int|string, etat: EtatExemplaire|string|null, nb: int|string}> $comptes */
        $comptes = $this->createQueryBuilder('m')
            ->select('m.id AS materielId', 'e.etat AS etat', 'COUNT(e.id) AS nb')
            ->leftJoin('m.exemplaires', 'e')
            ->groupBy('m.id', 'e.etat')
            ->getQuery()
            ->getScalarResult();

        // Etats connus, initialises a zero pour chaque materiel.
        $etatsVides = [];
        foreach (EtatExemplaire::cases() as $etat) {
            $etatsVides[$etat->value] = 0;
        }

        // Tous les materiels tries par nom : inclut ceux sans exemplaire (restent a zero).
        $parMateriel = [];
        foreach ($this->findBy([], ['nom' => 'ASC']) as $materiel) {
            $parMateriel[(int) $materiel->getId()] = ['materiel' => $materiel, 'etats' => $etatsVides, 'total' => 0];
        }

        foreach ($comptes as $ligne) {
            // etat NULL => materiel sans exemplaire (LEFT JOIN) : rien a compter.
            $etat = $ligne['etat'];
            if (null === $etat) {
                continue;
            }

            // Selon l'hydratation, e.etat peut revenir en enum ou en valeur brute (string).
            $cleEtat = $etat instanceof EtatExemplaire ? $etat->value : (string) $etat;
            $id = (int) $ligne['materielId'];
            $nb = (int) $ligne['nb'];

            if (isset($parMateriel[$id])) {
                $parMateriel[$id]['etats'][$cleEtat] = $nb;
                $parMateriel[$id]['total'] += $nb;
            }
        }

        return array_values($parMateriel);
    }

    /**
     * Catalogue cote emprunteur : chaque materiel avec son nombre d'exemplaires DISPONIBLE.
     *
     * La condition etat=DISPONIBLE est placee dans le WITH du LEFT JOIN (clause ON), et NON
     * dans le WHERE : ainsi un materiel sans aucun exemplaire disponible reste present avec un
     * compte a 0 (un WHERE transformerait le LEFT JOIN en INNER JOIN et l'exclurait).
     * getResult() est correct ici : GROUP BY m.id => une seule ligne par materiel, donc pas de
     * deduplication de root entity (contrairement a etatDuParc qui avait plusieurs etats par
     * materiel et imposait getScalarResult).
     *
     * @return list<array{materiel: Materiel, nbDisponibles: int}>
     */
    public function catalogueAvecDisponibilite(?int $categorieId = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m AS materiel', 'COUNT(e.id) AS nbDisponibles')
            ->leftJoin('m.exemplaires', 'e', 'WITH', 'e.etat = :disponible')
            ->setParameter('disponible', EtatExemplaire::DISPONIBLE->value)
            ->groupBy('m.id')
            ->orderBy('m.nom', 'ASC');

        if (null !== $categorieId) {
            $qb->andWhere('m.categorie = :cat')->setParameter('cat', $categorieId);
        }

        /** @var list<array{materiel: Materiel, nbDisponibles: int|string}> $lignes */
        $lignes = $qb->getQuery()->getResult();

        $catalogue = [];
        foreach ($lignes as $ligne) {
            $catalogue[] = ['materiel' => $ligne['materiel'], 'nbDisponibles' => (int) $ligne['nbDisponibles']];
        }

        return $catalogue;
    }
}
