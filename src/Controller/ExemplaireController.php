<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Exemplaire;
use App\Form\ExemplaireType;
use App\Repository\ExemplaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des exemplaires (unites physiques de l'inventaire).
 * Zone deja cloisonnee par access_control ^/gestion (US-2.3) ; #[IsGranted] en defense.
 */
#[Route('/gestion/exemplaire')]
#[IsGranted('ROLE_GESTIONNAIRE')]
final class ExemplaireController extends AbstractController
{
    #[Route('', name: 'app_exemplaire_index', methods: ['GET'])]
    public function index(ExemplaireRepository $exemplaires): Response
    {
        return $this->render('gestion/exemplaire/index.html.twig', [
            'exemplaires' => $exemplaires->findBy([], ['numeroInventaire' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_exemplaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $exemplaire = new Exemplaire();
        $form = $this->createForm(ExemplaireType::class, $exemplaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($exemplaire);
            $em->flush();
            $this->addFlash('success', 'Exemplaire cree.');

            return $this->redirectToRoute('app_exemplaire_index');
        }

        return $this->render('gestion/exemplaire/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_exemplaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Exemplaire $exemplaire, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ExemplaireType::class, $exemplaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Exemplaire modifie.');

            return $this->redirectToRoute('app_exemplaire_index');
        }

        return $this->render('gestion/exemplaire/edit.html.twig', [
            'exemplaire' => $exemplaire,
            'form'       => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_exemplaire_delete', methods: ['POST'])]
    public function delete(Request $request, Exemplaire $exemplaire, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer_exemplaire_' . $exemplaire->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_exemplaire_index');
        }

        // Iteration 3 : ajouter ici une garde si des prets sont rattaches a l'exemplaire
        // (FK RESTRICT pret -> exemplaire). Pas de pret a ce stade.
        $em->remove($exemplaire);
        $em->flush();
        $this->addFlash('success', 'Exemplaire supprime.');

        return $this->redirectToRoute('app_exemplaire_index');
    }
}
