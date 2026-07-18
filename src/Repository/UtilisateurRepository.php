<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * Page de tous les comptes pour l'administration (US-6.2), tries par nom puis prenom. Le terme
     * de recherche optionnel filtre EN SQL (jamais en PHP sur une collection chargee) sur nom, prenom
     * ou email, en sous-chaine. La casse est ignoree par la collation utf8mb4_unicode_ci de la base ;
     * les jokers LIKE (% et _) du terme sont echappes via ESCAPE '!' (un email contient souvent un
     * « _ », qui sinon sur-matcherait). Pagination par setFirstResult/setMaxResults.
     *
     * @return Paginator<Utilisateur>
     */
    public function findAllPourAdmin(int $page, int $parPage, ?string $recherche = null): Paginator
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->setFirstResult(($page - 1) * $parPage)
            ->setMaxResults($parPage);

        if (null !== $recherche && '' !== $recherche) {
            $terme = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $recherche);
            $qb->andWhere(
                "u.nom LIKE :recherche ESCAPE '!' "
                . "OR u.prenom LIKE :recherche ESCAPE '!' "
                . "OR u.email LIKE :recherche ESCAPE '!'",
            )->setParameter('recherche', '%' . $terme . '%');
        }

        return new Paginator($qb->getQuery(), false);
    }

    /**
     * Nombre de comptes SUPER_ADMIN encore actifs. Garde-fou anti-verrouillage : un super-admin
     * desactive ne peut plus se connecter, il ne compte donc pas comme repli. Empeche de retirer ou
     * de desactiver le dernier super-administrateur reellement utilisable (US-6.2).
     */
    public function countSuperAdminsActifs(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.role = :role')
            ->andWhere('u.estActif = true')
            ->setParameter('role', Role::SUPER_ADMIN->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
