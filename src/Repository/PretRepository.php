<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exemplaire;
use App\Entity\Pret;
use App\Enum\StatutPret;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pret>
 */
final class PretRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pret::class);
    }

    /**
     * Existe-t-il un pret VALIDE sur cet exemplaire chevauchant la periode [debut, fin] ?
     *
     * Test de chevauchement : deux intervalles [d1,f1] et [d2,f2] se chevauchent ssi
     * d1 < f2 ET f1 > d2. Les inegalites sont STRICTES : un pret finissant a l'instant ou
     * un autre commence (relais) ne chevauche pas. Seuls les prets VALIDE reservent
     * l'exemplaire (une DEMANDE en attente ne bloque pas ; REFUSE/ANNULE/RETOURNE non plus).
     * Requete soutenue par l'index idx_pret_dispo (id_exemplaire, statut, date_debut, date_fin).
     */
    public function existePretChevauchant(Exemplaire $exemplaire, \DateTimeImmutable $debut, \DateTimeImmutable $fin): bool
    {
        $n = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.exemplaire = :exemplaire')
            ->andWhere('p.statut = :valide')
            ->andWhere('p.dateDebut < :fin')
            ->andWhere('p.dateFin > :debut')
            ->setParameter('exemplaire', $exemplaire)
            ->setParameter('valide', StatutPret::VALIDE->value)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();

        return $n > 0;
    }

    /**
     * Les demandes en attente de traitement (statut DEMANDE), les plus anciennes d'abord.
     *
     * @return Pret[]
     */
    public function findDemandesEnAttente(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :demande')
            ->setParameter('demande', StatutPret::DEMANDE->value)
            ->orderBy('p.dateDemande', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Les prets en cours (statut VALIDE), les plus anciens d'abord. Sert a la liste des retours
     * a enregistrer cote gestionnaire.
     *
     * @return Pret[]
     */
    public function findPretsEnCours(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :valide')
            ->setParameter('valide', StatutPret::VALIDE->value)
            ->orderBy('p.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
