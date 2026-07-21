<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exemplaire;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\StatutPret;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pret>
 */
class PretRepository extends ServiceEntityRepository
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
        // S'appuie sur l'index composite idx_pret_dispo
        // (id_exemplaire, statut, date_debut, date_fin) : les deux criteres
        // d'egalite precedent les criteres d'intervalle, ce qui permet a
        // l'index d'etre exploite sur toute sa longueur.
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

    /**
     * Tous les prets d'un emprunteur, les plus recents d'abord. Sert a l'ecran « Mes prets »
     * (BF-5). Le filtre par emprunteur est la premiere garde : on ne requete que ses prets.
     *
     * @return Pret[]
     */
    public function findByEmprunteur(Utilisateur $emprunteur): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.emprunteur = :emprunteur')
            ->setParameter('emprunteur', $emprunteur)
            ->orderBy('p.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Prets VALIDE non retournes dont la date de fin tombe dans la plage donnee (la veille, calculee
     * par l'appelant) et pour lesquels le rappel d'echeance n'a pas encore ete envoye (idempotence).
     *
     * @return Pret[]
     */
    public function findPourRappelEcheance(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.dateRetour IS NULL')
            ->andWhere('p.dateFin BETWEEN :debut AND :fin')
            ->andWhere('p.rappelEcheanceEnvoyeAt IS NULL')
            ->setParameter('statut', StatutPret::VALIDE->value)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('p.dateFin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Prets VALIDE non retournes dont la date de fin est deja depassee et pour lesquels l'alerte de
     * retard n'a pas encore ete envoyee (idempotence).
     *
     * @return Pret[]
     */
    public function findEnRetard(\DateTimeImmutable $maintenant): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.dateRetour IS NULL')
            ->andWhere('p.dateFin < :maintenant')
            ->andWhere('p.retardNotifieAt IS NULL')
            ->setParameter('statut', StatutPret::VALIDE->value)
            ->setParameter('maintenant', $maintenant)
            ->orderBy('p.dateFin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Prets VALIDE dont la periode chevauche la fenetre [debut, fin] demandee par le calendrier.
     * Chevauchement par inegalites strictes (RG-4) : le pret commence avant la fin de la fenetre
     * ET se termine apres son debut. Jointures explicites pour eviter le N+1 a la serialisation.
     *
     * @return Pret[]
     */
    public function findPourCalendrier(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('e', 'm')
            ->join('p.exemplaire', 'e')
            ->join('e.materiel', 'm')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.dateDebut < :fin')
            ->andWhere('p.dateFin > :debut')
            ->setParameter('statut', StatutPret::VALIDE->value)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('p.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Nombre de prets actuellement en cours (VALIDE, non retournes). */
    public function countPretsEnCours(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.dateRetour IS NULL')
            ->setParameter('statut', StatutPret::VALIDE->value)
            ->getQuery()->getSingleScalarResult();
    }

    /** Nombre de demandes en attente de traitement (DEMANDE). */
    public function countDemandesEnAttente(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', StatutPret::DEMANDE->value)
            ->getQuery()->getSingleScalarResult();
    }

    /** Nombre de prets en retard (VALIDE non retournes dont la date de fin est depassee). */
    public function countPretsEnRetard(\DateTimeImmutable $maintenant): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.dateRetour IS NULL')
            ->andWhere('p.dateFin < :maintenant')
            ->setParameter('statut', StatutPret::VALIDE->value)
            ->setParameter('maintenant', $maintenant)
            ->getQuery()->getSingleScalarResult();
    }

    /** Nombre d'exemplaires distincts actuellement engages par un pret VALIDE actif. */
    public function countExemplairesEngages(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.exemplaire)')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.dateRetour IS NULL')
            ->setParameter('statut', StatutPret::VALIDE->value)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * Top des materiels les plus empruntes (tous statuts confondus hors annules/refuses).
     *
     * @return list<array{materiel: string, total: int}>
     */
    public function topMaterielsEmpruntes(int $limite = 5): array
    {
        $resultats = $this->createQueryBuilder('p')
            ->select('m.nom AS materiel', 'COUNT(p.id) AS total')
            ->join('p.exemplaire', 'e')
            ->join('e.materiel', 'm')
            ->andWhere('p.statut NOT IN (:exclus)')
            ->setParameter('exclus', [StatutPret::ANNULE->value, StatutPret::REFUSE->value])
            ->groupBy('m.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()->getResult();

        return array_map(
            static fn (array $r): array => ['materiel' => (string) $r['materiel'], 'total' => (int) $r['total']],
            $resultats,
        );
    }
}
