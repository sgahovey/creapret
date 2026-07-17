<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RefusPret;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\ResultatValidation;
use App\Enum\StatutPret;
use App\Enum\TypeActionJournal;
use App\Form\RefusPretType;
use App\Repository\PretRepository;
use App\Service\JournalAdminService;
use App\Service\NotificationService;
use App\Service\PretService;
use Doctrine\ORM\EntityManagerInterface;
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
    public function valider(Request $request, Pret $pret, PretService $service, NotificationService $notifications, JournalAdminService $journal, EntityManagerInterface $em): Response
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

        // Notifier apres commit du service (fait persiste). CONFLIT => le pret est REFUSE (motif pose).
        if (ResultatValidation::VALIDE === $resultat) {
            $notifications->notifierValidation($pret);
            // Journalisation de la decision (le CONFLIT est un refus systeme, non attribue au gestionnaire).
            $journal->enregistrer(TypeActionJournal::PRET_VALIDATION, $validateur, $pret->getEmprunteur());
            $em->flush();
        } elseif (ResultatValidation::CONFLIT === $resultat) {
            $notifications->notifierRefus($pret);
        }

        return $this->redirectToRoute('app_gestion_prets');
    }

    #[Route('/{id}/refuser', name: 'app_gestion_pret_refuser', methods: ['GET', 'POST'])]
    public function refuser(Request $request, Pret $pret, PretService $service, NotificationService $notifications, JournalAdminService $journal, EntityManagerInterface $em): Response
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
            $notifications->notifierRefus($pret);
            $journal->enregistrer(TypeActionJournal::PRET_REFUS, $validateur, $pret->getEmprunteur(), $refus->motif);
            $em->flush();
            $this->addFlash('success', 'Demande refusee.');

            return $this->redirectToRoute('app_gestion_prets');
        }

        return $this->render('gestion_pret/refuser.html.twig', [
            'pret' => $pret,
            'form' => $form,
        ]);
    }

    #[Route('/retours', name: 'app_gestion_retours', methods: ['GET'])]
    public function retours(PretRepository $prets): Response
    {
        return $this->render('gestion_pret/retours.html.twig', [
            'prets' => $prets->findPretsEnCours(),
        ]);
    }

    #[Route('/{id}/retour', name: 'app_gestion_pret_retour', methods: ['POST'])]
    public function retour(Request $request, Pret $pret, PretService $service, NotificationService $notifications, JournalAdminService $journal, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('retour' . $pret->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_gestion_retours');
        }

        $gestionnaire = $this->getUser();
        \assert($gestionnaire instanceof Utilisateur);

        $dommage = $request->request->getBoolean('dommage');
        $service->enregistrerRetour($pret, $dommage);
        $notifications->notifierRetour($pret);
        $journal->enregistrer(TypeActionJournal::PRET_RETOUR, $gestionnaire, $pret->getEmprunteur(), $dommage ? 'Retour avec dommage' : null);
        $em->flush();
        $this->addFlash('success', $dommage
            ? 'Retour enregistre : exemplaire mis en maintenance.'
            : 'Retour enregistre : exemplaire disponible.');

        return $this->redirectToRoute('app_gestion_retours');
    }

    #[Route('/calendrier', name: 'app_gestion_calendrier', methods: ['GET'])]
    public function calendrier(): Response
    {
        return $this->render('gestion_pret/calendrier.html.twig');
    }
}
