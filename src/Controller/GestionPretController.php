<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RefusPret;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\ResultatValidation;
use App\Enum\StatutPret;
use App\Form\RefusPretType;
use App\Repository\PretRepository;
use App\Service\PretService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestion/prets')]
#[IsGranted('ROLE_GESTIONNAIRE')]
final class GestionPretController extends AbstractController
{
    #[Route('', name: 'app_gestion_prets', methods: ['GET'])]
    public function index(PretRepository $prets): Response
    {
        return $this->render('gestion_pret/index.html.twig', [
            'demandes' => $prets->findDemandesEnAttente(),
        ]);
    }

    #[Route('/{id}/valider', name: 'app_gestion_pret_valider', methods: ['POST'])]
    public function valider(Request $request, Pret $pret, PretService $service): Response
    {
        if (!$this->isCsrfTokenValid('valider' . $pret->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_gestion_prets');
        }

        $validateur = $this->getUser();
        \assert($validateur instanceof Utilisateur);

        $resultat = $service->valider($pret, $validateur);
        match ($resultat) {
            ResultatValidation::VALIDE      => $this->addFlash('success', 'Pret valide.'),
            ResultatValidation::CONFLIT     => $this->addFlash('warning', 'Refuse : un pret concurrent a ete valide sur cette periode.'),
            ResultatValidation::DEJA_TRAITE => $this->addFlash('info', 'Cette demande a deja ete traitee.'),
        };

        return $this->redirectToRoute('app_gestion_prets');
    }

    #[Route('/{id}/refuser', name: 'app_gestion_pret_refuser', methods: ['GET', 'POST'])]
    public function refuser(Request $request, Pret $pret, PretService $service): Response
    {
        // On ne refuse qu'une demande encore en attente.
        if (StatutPret::DEMANDE !== $pret->getStatut()) {
            $this->addFlash('info', 'Cette demande a deja ete traitee.');

            return $this->redirectToRoute('app_gestion_prets');
        }

        $refus = new RefusPret();
        $form = $this->createForm(RefusPretType::class, $refus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $validateur = $this->getUser();
            \assert($validateur instanceof Utilisateur);
            \assert(null !== $refus->motif);

            $service->refuser($pret, $validateur, $refus->motif);
            $this->addFlash('success', 'Demande refusee.');

            return $this->redirectToRoute('app_gestion_prets');
        }

        return $this->render('gestion_pret/refuser.html.twig', [
            'pret' => $pret,
            'form' => $form,
        ]);
    }
}
