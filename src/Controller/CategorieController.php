<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Categorie;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des categories du catalogue.
 * Zone deja cloisonnee par access_control ^/gestion (bout A) ; #[IsGranted] en defense.
 */
#[Route('/gestion/categorie')]
#[IsGranted('ROLE_GESTIONNAIRE')]
final class CategorieController extends AbstractController
{
    #[Route('', name: 'app_categorie_index', methods: ['GET'])]
    public function index(CategorieRepository $categories): Response
    {
        return $this->render('gestion/categorie/index.html.twig', [
            'categories' => $categories->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/nouvelle', name: 'app_categorie_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $categorie = new Categorie();
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($categorie);
            $em->flush();
            $this->addFlash('success', 'Catégorie créée.');

            return $this->redirectToRoute('app_categorie_index');
        }

        return $this->render('gestion/categorie/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_categorie_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Catégorie modifiée.');

            return $this->redirectToRoute('app_categorie_index');
        }

        return $this->render('gestion/categorie/edit.html.twig', [
            'categorie' => $categorie,
            'form'      => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_categorie_delete', methods: ['POST'])]
    public function delete(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('supprimer_categorie_' . $categorie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_categorie_index');
        }

        // Garde FK RESTRICT (DC-10) : refus si des materiels sont rattaches.
        // Verification prealable -> message clair, pas d'exception 500.
        if (\count($categorie->getMateriels()) > 0) {
            $this->addFlash('danger', 'Suppression impossible : des matériels sont rattachés à cette catégorie.');

            return $this->redirectToRoute('app_categorie_index');
        }

        $em->remove($categorie);
        $em->flush();
        $this->addFlash('success', 'Catégorie supprimée.');

        return $this->redirectToRoute('app_categorie_index');
    }
}
