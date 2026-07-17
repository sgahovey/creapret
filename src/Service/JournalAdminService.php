<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JournalAdmin;
use App\Entity\Utilisateur;
use App\Enum\TypeActionJournal;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre les decisions d'administration sur les prets dans le journal d'accountability (US-5.3).
 *
 * Contrat persist-only : enregistrer() ne fait qu'un persist(), le flush reste a la charge de
 * l'appelant. Ici PretService a deja flushe l'action metier au moment de l'appel, donc le controleur
 * declenche un flush dedie apres enregistrer() (la trace n'est pas strictement atomique avec l'action,
 * choix assume pour un journal d'accountability : l'action reste la source de verite).
 *
 * Acteur et cible sont FIGES (identifiant + libelle au moment de l'action) : la trace reste lisible
 * meme apres desactivation, renommage ou suppression des comptes concernes, sans jointure.
 */
final readonly class JournalAdminService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function enregistrer(
        TypeActionJournal $type,
        Utilisateur $acteur,
        ?Utilisateur $cible = null,
        ?string $details = null,
    ): void {
        $this->em->persist(new JournalAdmin(
            typeAction: $type,
            acteurId: (int) $acteur->getId(),
            acteurLibelle: $acteur->getNomComplet(),
            cibleId: $cible?->getId(),
            cibleLibelle: $cible?->getNomComplet(),
            details: $details,
        ));
    }
}
