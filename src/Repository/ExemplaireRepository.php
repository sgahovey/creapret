<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Enum\EtatExemplaire;
use App\Enum\StatutPret;
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

    /**
     * Nombre d'exemplaires d'un materiel LIBRES sur la periode [debut, fin] (RG-4).
     *
     * Un exemplaire est libre s'il est DISPONIBLE et n'a AUCUN pret VALIDE chevauchant la
     * periode. Le NOT EXISTS (sous-requete correlee) exprime « aucun pret bloquant » : il
     * court-circuite au premier chevauchement trouve et evite les doublons qu'un JOIN sur la
     * relation 1-N produirait. Meme test de chevauchement qu'au niveau unitaire
     * (date_debut < :fin AND date_fin > :debut, inegalites strictes).
     */
    public function compterLibresSurPeriode(Materiel $materiel, \DateTimeImmutable $debut, \DateTimeImmutable $fin): int
    {
        $sousRequete = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Pret::class, 'p')
            ->where('p.exemplaire = e')
            ->andWhere('p.statut = :valide')
            ->andWhere('p.dateDebut < :fin')
            ->andWhere('p.dateFin > :debut')
            ->getDQL();

        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.materiel = :materiel')
            ->andWhere('e.etat = :disponible')
            ->andWhere('NOT EXISTS (' . $sousRequete . ')')
            ->setParameter('materiel', $materiel)
            ->setParameter('disponible', EtatExemplaire::DISPONIBLE->value)
            ->setParameter('valide', StatutPret::VALIDE->value)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Le premier exemplaire d'un materiel LIBRE sur la periode [debut, fin], ou null.
     *
     * Meme critere que compterLibresSurPeriode : DISPONIBLE et sans pret VALIDE chevauchant
     * (NOT EXISTS, dateDebut < :fin AND dateFin > :debut, inegalites strictes). Retourne une
     * entite (pas un compte) pour fixer l'exemplaire d'une demande (id_exemplaire NOT NULL).
     * Ordre stable par numero_inventaire pour un choix deterministe.
     */
    public function trouverUnLibreSurPeriode(Materiel $materiel, \DateTimeImmutable $debut, \DateTimeImmutable $fin): ?Exemplaire
    {
        $sousRequete = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Pret::class, 'p')
            ->where('p.exemplaire = e')
            ->andWhere('p.statut = :valide')
            ->andWhere('p.dateDebut < :fin')
            ->andWhere('p.dateFin > :debut')
            ->getDQL();

        $resultat = $this->createQueryBuilder('e')
            ->andWhere('e.materiel = :materiel')
            ->andWhere('e.etat = :disponible')
            ->andWhere('NOT EXISTS (' . $sousRequete . ')')
            ->setParameter('materiel', $materiel)
            ->setParameter('disponible', EtatExemplaire::DISPONIBLE->value)
            ->setParameter('valide', StatutPret::VALIDE->value)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('e.numeroInventaire', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // getOneOrNullResult() renvoie mixed : on garantit le type de retour de la methode.
        return $resultat instanceof Exemplaire ? $resultat : null;
    }
}
