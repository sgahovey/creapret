<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Indicateurs du tableau de bord de pilotage (US-5.1). Objet de transfert immuable :
 * il porte les valeurs deja calculees par le StatistiqueService vers la vue.
 */
final readonly class TableauBordStats
{
    /**
     * @param list<array{materiel: string, total: int}> $topMateriels
     */
    public function __construct(
        public int $pretsEnCours,
        public int $demandesEnAttente,
        public int $pretsEnRetard,
        public float $tauxOccupation,
        public int $exemplairesEngages,
        public int $exemplairesTotal,
        public array $topMateriels,
    ) {
    }
}
