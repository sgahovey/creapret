<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\EtatExemplaire;
use App\Repository\MaterielRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vue consolidee de l'etat du parc (CP8) : nombre d'exemplaires par etat, par materiel.
 * Zone deja cloisonnee par access_control ^/gestion ; #[IsGranted] en defense.
 */
#[Route('/gestion/parc')]
#[IsGranted('ROLE_GESTIONNAIRE')]
final class ParcController extends AbstractController
{
    #[Route('', name: 'app_parc_index', methods: ['GET'])]
    public function index(MaterielRepository $materiels): Response
    {
        return $this->render('gestion/parc/index.html.twig', [
            'parc'  => $materiels->etatDuParc(),
            'etats' => EtatExemplaire::cases(),
        ]);
    }
}
