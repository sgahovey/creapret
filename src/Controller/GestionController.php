<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace de gestion (gestionnaire et super-admin par hierarchie de roles).
 * Defense en profondeur : #[IsGranted] au niveau classe EN PLUS du cloisonnement
 * access_control ^/gestion declare dans security.yaml.
 */
#[Route('/gestion')]
#[IsGranted('ROLE_GESTIONNAIRE')]
final class GestionController extends AbstractController
{
    #[Route('', name: 'app_gestion', methods: ['GET'])]
    public function accueil(): Response
    {
        return $this->render('gestion/index.html.twig');
    }
}
