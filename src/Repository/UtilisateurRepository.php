<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Les utilisateurs habilites a traiter les demandes de pret (gestionnaires et super-admins),
     * actifs uniquement. Destinataires de la notification de demande creee (US-4.2).
     *
     * @return Utilisateur[]
     */
    public function findGestionnaires(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.role IN (:roles)')
            ->andWhere('u.estActif = true')
            ->setParameter('roles', [Role::GESTIONNAIRE->value, Role::SUPER_ADMIN->value])
            ->getQuery()
            ->getResult();
    }
}
