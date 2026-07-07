<?php

declare(strict_types=1);

namespace App\Controller\Gestion;

use App\Service\StatistiqueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de bord de pilotage du parc (US-5.1), destine au gestionnaire (aide a la decision :
 * materiel le plus emprunte, taux d'utilisation, prets en retard). Le super-administrateur y accede
 * par heritage de role (hierarchie cumulative). Presente les indicateurs cles et le classement des
 * materiels les plus empruntes.
 */
#[Route('/gestion/tableau-de-bord', name: 'app_gestion_tableau_de_bord', methods: ['GET'])]
#[IsGranted('ROLE_GESTIONNAIRE')]
final class TableauDeBordController extends AbstractController
{
    public function __invoke(StatistiqueService $statistiques): Response
    {
        return $this->render('gestion/tableau_de_bord/index.html.twig', [
            'stats' => $statistiques->calculerTableauBord(),
        ]);
    }
}
