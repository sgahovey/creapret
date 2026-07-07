<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Pret;

/**
 * Transforme des prets en evenements consommables par FullCalendar.
 *
 * Minimisation RGPD : le titre affiche identifie le MATERIEL (nom + numero d'inventaire), jamais
 * l'emprunteur. L'identite de l'emprunteur, que le gestionnaire est habilite a voir, est placee
 * dans extendedProps (accessible au clic), pas sur la grille publique du calendrier.
 */
final readonly class PretCalendarSerializer
{
    /**
     * @param Pret[] $prets
     *
     * @return list<array<string, mixed>>
     */
    public function toCalendarEvents(array $prets): array
    {
        $evenements = [];
        foreach ($prets as $pret) {
            $exemplaire = $pret->getExemplaire();
            $materiel = $exemplaire->getMateriel();

            $evenements[] = [
                'id'            => $pret->getId(),
                'title'         => sprintf('%s (%s)', $materiel->getNom(), $exemplaire->getNumeroInventaire()),
                'start'         => $pret->getDateDebut()->format(\DateTimeInterface::ATOM),
                'end'           => $pret->getDateFin()->format(\DateTimeInterface::ATOM),
                'color'         => '#0f6e56',
                'extendedProps' => [
                    'statut'           => $pret->getStatut()->libelle(),
                    'materiel'         => $materiel->getNom(),
                    'numeroInventaire' => $exemplaire->getNumeroInventaire(),
                    // Visible au clic par le gestionnaire (habilite), pas sur la grille.
                    'emprunteur' => $pret->getEmprunteur()->getPrenom() . ' ' . $pret->getEmprunteur()->getNom(),
                ],
            ];
        }

        return $evenements;
    }
}
