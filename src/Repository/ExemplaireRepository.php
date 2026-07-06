<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Enum\EtatExemplaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exemplaire>
 */
class ExemplaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exemplaire::class);
    }

    /**
     * Nombre d'exemplaires DISPONIBLE d'un materiel donne (disponibilite statique).
     * La disponibilite sur une periode (RG-4, exemplaires non engages par un pret
     * chevauchant) sera calculee en iteration 3.
     */
    public function compterDisponibles(Materiel $materiel): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.materiel = :materiel')
            ->andWhere('e.etat = :disponible')
            ->setParameter('materiel', $materiel)
            ->setParameter('disponible', EtatExemplaire::DISPONIBLE->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
