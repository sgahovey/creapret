<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\JournalAdmin;
use App\Enum\TypeActionJournal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalAdmin>
 */
class JournalAdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalAdmin::class);
    }

    /**
     * Page du journal pour la consultation super-administrateur (les plus recentes d'abord), avec
     * filtre optionnel par type d'action. Aucune jointure : acteur/cible sont figes dans l'entree.
     *
     * @return Paginator<JournalAdmin>
     */
    public function findPourAdmin(int $page, int $limit = 25, ?TypeActionJournal $typeAction = null): Paginator
    {
        $qb = $this->createQueryBuilder('j')
            ->orderBy('j.dateAction', 'DESC')
            ->addOrderBy('j.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($typeAction instanceof TypeActionJournal) {
            $qb->andWhere('j.typeAction = :type')->setParameter('type', $typeAction);
        }

        return new Paginator($qb->getQuery(), false);
    }

    /**
     * Supprime les entrees anterieures au seuil de conservation (purge RGPD). Borne sur la seule
     * date, jamais par id ni acteur, pour preserver le caractere append-only du journal.
     *
     * @return int nombre d'entrees supprimees
     */
    public function purgerAvant(\DateTimeImmutable $seuil): int
    {
        return (int) $this->createQueryBuilder('j')
            ->delete()
            ->where('j.dateAction < :seuil')
            ->setParameter('seuil', $seuil)
            ->getQuery()
            ->execute();
    }

    /**
     * Compte les entrees anterieures au seuil, sans rien supprimer (mode dry-run de la purge).
     */
    public function compterAvant(\DateTimeImmutable $seuil): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->where('j.dateAction < :seuil')
            ->setParameter('seuil', $seuil)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
