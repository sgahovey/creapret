<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Materiel;
use App\Form\MaterielType;
use App\Repository\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des materiels du catalogue.
 * Zone deja cloisonnee par access_control ^/gestion (bout A) ; #[IsGranted] en defense.
 */
#[Route('/gestion/materiel')]
#[IsGranted('ROLE_GESTIONNAIRE')]
final class MaterielController extends AbstractController
{
    #[Route('', name: 'app_materiel_index', methods: ['GET'])]
    public function index(MaterielRepository $materiels): Response
    {
        return $this->render('gestion/materiel/index.html.twig', [
            'materiels' => $materiels->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_materiel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $materiel = new Materiel();
        $form = $this->createForm(MaterielType::class, $materiel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($materiel);
            $em->flush();
            $this->addFlash('success', 'Materiel cree.');

            return $this->redirectToRoute('app_materiel_index');
        }

        return $this->render('gestion/materiel/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_materiel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Materiel $materiel, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(MaterielType::class, $materiel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Materiel modifie.');

            return $this->redirectToRoute('app_materiel_index');
        }

        return $this->render('gestion/materiel/edit.html.twig', [
            'materiel' => $materiel,
            'form'     => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_materiel_delete', methods: ['POST'])]
    public function delete(Request $request, Materiel $materiel, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer_materiel_' . $materiel->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_materiel_index');
        }

        // Garde FK RESTRICT (DC-10) : refus si des exemplaires sont rattaches.
        if (\count($materiel->getExemplaires()) > 0) {
            $this->addFlash('danger', 'Suppression impossible : des exemplaires sont rattaches a ce materiel.');

            return $this->redirectToRoute('app_materiel_index');
        }

        $em->remove($materiel);
        $em->flush();
        $this->addFlash('success', 'Materiel supprime.');

        return $this->redirectToRoute('app_materiel_index');
    }
}
