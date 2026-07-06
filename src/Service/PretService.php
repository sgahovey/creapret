<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\ResultatValidation;
use App\Enum\StatutPret;
use App\Repository\PretRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique metier de validation/refus des prets (CU-07).
 *
 * La validation est l'operation critique de concurrence (RG-1 : jamais deux prets VALIDE
 * chevauchants sur le meme exemplaire). Elle s'execute dans une transaction avec un verrou
 * pessimiste (PESSIMISTIC_WRITE = SELECT ... FOR UPDATE) sur la ligne de l'exemplaire, PUIS
 * une reverification du chevauchement SOUS ce verrou. Ce n'est pas le verrou seul qui garantit
 * RG-1, mais la reverification effectuee dans la fenetre exclusive : le verrou serialise les
 * validations concurrentes, la reverification decide sur un etat stable.
 */
final class PretService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PretRepository $prets,
    ) {
    }

    /**
     * Valide un pret sous verrou pessimiste (CU-07, RG-1).
     */
    public function valider(Pret $pret, Utilisateur $validateur): ResultatValidation
    {
        $this->em->beginTransaction();
        try {
            $exemplaire = $pret->getExemplaire();
            // Verrou exclusif sur la ligne de l'exemplaire : toute autre validation concurrente
            // portant sur le meme exemplaire devra attendre la fin de cette transaction.
            $this->em->lock($exemplaire, LockMode::PESSIMISTIC_WRITE);

            // Idempotence : le pret a-t-il deja ete traite entre-temps ?
            if (StatutPret::DEMANDE !== $pret->getStatut()) {
                $this->em->commit();

                return ResultatValidation::DEJA_TRAITE;
            }

            // Reverification RG-1 SOUS le verrou : un pret VALIDE chevauche-t-il deja ?
            if ($this->prets->existePretChevauchant($exemplaire, $pret->getDateDebut(), $pret->getDateFin())) {
                $pret->setStatut(StatutPret::REFUSE)
                    ->setValidateur($validateur)
                    ->setDateValidation(new \DateTimeImmutable())
                    ->setMotifRefus('Un pret concurrent a ete valide sur cette periode.');
                $this->em->flush();
                $this->em->commit();

                return ResultatValidation::CONFLIT;
            }

            // Aucun conflit : on valide.
            $pret->setStatut(StatutPret::VALIDE)
                ->setValidateur($validateur)
                ->setDateValidation(new \DateTimeImmutable());
            $this->em->flush();
            $this->em->commit();

            return ResultatValidation::VALIDE;
        } catch (\Throwable $e) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }

            throw $e;
        }
    }

    /**
     * Refus explicite d'un pret par un gestionnaire (avec motif). Sans verrou : un refus ne cree
     * aucun engagement concurrent. Idempotent sur le statut DEMANDE.
     */
    public function refuser(Pret $pret, Utilisateur $validateur, string $motif): void
    {
        if (StatutPret::DEMANDE !== $pret->getStatut()) {
            return;
        }

        $pret->setStatut(StatutPret::REFUSE)
            ->setValidateur($validateur)
            ->setDateValidation(new \DateTimeImmutable())
            ->setMotifRefus($motif);
        $this->em->flush();
    }
}
