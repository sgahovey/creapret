<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages legales publiques (US-5.4) : mentions legales, politique de confidentialite
 * et declaration d'accessibilite RGAA. Accessibles sans authentification (cf. les
 * regles PUBLIC_ACCESS dans config/packages/security.yaml). Aucune logique metier :
 * de simples pages de contenu statique rendues par Twig.
 */
final class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'app_mentions_legales', methods: ['GET'])]
    public function mentionsLegales(): Response
    {
        return $this->render('legal/mentions_legales.html.twig');
    }

    #[Route('/confidentialite', name: 'app_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->render('legal/confidentialite.html.twig');
    }

    #[Route('/accessibilite', name: 'app_accessibilite', methods: ['GET'])]
    public function accessibilite(): Response
    {
        return $this->render('legal/accessibilite.html.twig');
    }
}
