<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Deja authentifie : pas de raison de revoir le formulaire.
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/connexion.html.twig', [
            'dernierEmail' => $authenticationUtils->getLastUsername(),
            'erreur'       => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * @codeCoverageIgnore Interceptee par le pare-feu (cle 'logout' de security.yaml) : jamais executee.
     */
    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        // Interceptee par le firewall (cle 'logout' de security.yaml) : jamais executee.
        throw new \LogicException('Cette methode est interceptee par le pare-feu de deconnexion.');
    }
}
