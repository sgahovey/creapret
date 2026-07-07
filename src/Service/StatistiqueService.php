<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\TableauBordStats;
use App\Repository\ExemplaireRepository;
use App\Repository\PretRepository;

/**
 * Calcule les indicateurs de pilotage du parc pour le tableau de bord super-administrateur (US-5.1).
 * S'appuie sur des agregats scalaires (COUNT), sans hydratation d'entites, pour rester performant.
 */
final readonly class StatistiqueService
{
    private const int TOP_MATERIELS = 5;

    public function __construct(
        private PretRepository $prets,
        private ExemplaireRepository $exemplaires,
    ) {
    }

    public function calculerTableauBord(): TableauBordStats
    {
        $maintenant = new \DateTimeImmutable('now');
        $engages = $this->prets->countExemplairesEngages();
        $total = $this->exemplaires->countTotal();

        return new TableauBordStats(
            pretsEnCours: $this->prets->countPretsEnCours(),
            demandesEnAttente: $this->prets->countDemandesEnAttente(),
            pretsEnRetard: $this->prets->countPretsEnRetard($maintenant),
            tauxOccupation: $this->tauxOccupation($engages, $total),
            exemplairesEngages: $engages,
            exemplairesTotal: $total,
            topMateriels: $this->prets->topMaterielsEmpruntes(self::TOP_MATERIELS),
        );
    }

    /** Taux d'occupation en pourcentage, avec garde contre la division par zero (parc vide). */
    private function tauxOccupation(int $engages, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round($engages / $total * 100, 1);
    }
}
