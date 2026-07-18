<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\DemandePret;
use App\Entity\Materiel;
use App\Form\DemandePretType;
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
    public function show(Request $request, Materiel $materiel, ExemplaireRepository $exemplaires): Response
    {
        // Disponibilite sur periode (RG-4) : optionnelle, pilotee par ?debut & ?fin.
        $debutSaisi = $request->query->getString('debut');
        $finSaisie = $request->query->getString('fin');
        $nbSurPeriode = null;
        $erreurPeriode = null;
        $debut = null;
        $fin = null;

        if ('' !== $debutSaisi || '' !== $finSaisie) {
            $debut = \DateTimeImmutable::createFromFormat('Y-m-d', $debutSaisi) ?: null;
            $fin = \DateTimeImmutable::createFromFormat('Y-m-d', $finSaisie) ?: null;

            if (null === $debut || null === $fin) {
                $erreurPeriode = 'Dates invalides : utilisez le format jour/mois/année.';
            } elseif ($fin <= $debut) {
                $erreurPeriode = 'La date de fin doit être postérieure à la date de début.';
            } else {
                $nbSurPeriode = $exemplaires->compterLibresSurPeriode($materiel, $debut, $fin);
            }
        }

        // Formulaire de demande : propose uniquement si une periode valide est saisie et
        // qu'il reste au moins un exemplaire libre. Traite par PretController::demander.
        $formDemande = null;
        if (null !== $nbSurPeriode && $nbSurPeriode > 0) {
            // A ce stade, debut/fin sont des DateTimeImmutable valides : nbSurPeriode n'est
            // calcule que pour une periode syntaxiquement correcte (fin > debut).
            $demande = new DemandePret();
            $demande->debut = $debut;
            $demande->fin = $fin;
            $formDemande = $this->createForm(DemandePretType::class, $demande, [
                'action' => $this->generateUrl('app_pret_demander', ['id' => $materiel->getId()]),
            ])->createView();
        }

        $response = $this->render('catalogue/show.html.twig', [
            'materiel'      => $materiel,
            'nbDisponibles' => $exemplaires->compterDisponibles($materiel),
            'debut'         => $debut instanceof \DateTimeImmutable ? $debut->format('Y-m-d') : $debutSaisi,
            'fin'           => $fin instanceof \DateTimeImmutable ? $fin->format('Y-m-d') : $finSaisie,
            'nbSurPeriode'  => $nbSurPeriode,
            'erreurPeriode' => $erreurPeriode,
            'formDemande'   => $formDemande,
        ]);

        // Donnee temps reel : jamais mise en cache (ni navigateur ni proxy).
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
