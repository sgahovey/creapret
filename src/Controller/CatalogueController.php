<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Materiel;
use App\Repository\CategorieRepository;
use App\Repository\ExemplaireRepository;
use App\Repository\MaterielRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Consultation du catalogue cote emprunteur (lecture seule, BF-2).
 * Reservee aux utilisateurs connectes : access_control ^/catalogue + #[IsGranted] en defense.
 */
#[Route('/catalogue')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class CatalogueController extends AbstractController
{
    #[Route('', name: 'app_catalogue_index', methods: ['GET'])]
    public function index(Request $request, MaterielRepository $materiels, CategorieRepository $categories): Response
    {
        $categorieId = $request->query->getInt('categorie') ?: null;

        return $this->render('catalogue/index.html.twig', [
            'catalogue'       => $materiels->catalogueAvecDisponibilite($categorieId),
            'categories'      => $categories->findBy([], ['nom' => 'ASC']),
            'categorieActive' => $categorieId,
        ]);
    }

    #[Route('/{id}', name: 'app_catalogue_show', methods: ['GET'])]
    public function show(Materiel $materiel, ExemplaireRepository $exemplaires): Response
    {
        return $this->render('catalogue/show.html.twig', [
            'materiel'      => $materiel,
            'nbDisponibles' => $exemplaires->compterDisponibles($materiel),
        ]);
    }
}
