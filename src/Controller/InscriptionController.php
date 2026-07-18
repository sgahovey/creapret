<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\InscriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class InscriptionController extends AbstractController
{
    public function __construct(
        private readonly string $versionCgu,
    ) {
    }

    #[Route('/inscription', name: 'app_inscription', methods: ['GET', 'POST'])]
    public function inscription(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        $utilisateur = new Utilisateur();
        $form = $this->createForm(InscriptionType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            $utilisateur->setMotDePasseHash($hasher->hashPassword($utilisateur, $plain));

            // Preuve de consentement RGPD (case CGU cochee et validee par IsTrue).
            $utilisateur->consentir($this->versionCgu);
            // role = EMPRUNTEUR et estActif = true : valeurs par defaut de l'entite.

            $em->persist($utilisateur);
            $em->flush();

            $this->addFlash('success', 'Votre compte a été créé. Vous pouvez désormais vous connecter.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/inscription.html.twig', [
            'formulaire' => $form,
        ]);
    }
}
