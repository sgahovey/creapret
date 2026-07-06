<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\DemandePret;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Form\DemandePretType;
use App\Repository\ExemplaireRepository;
use App\Repository\PretRepository;
use App\Security\Voter\PretVoter;
use App\Service\PretService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PretController extends AbstractController
{
    /**
     * Enregistre une demande de pret (CU-04) : statut DEMANDE, sur le premier exemplaire libre.
     *
     * Validation en deux temps : le formulaire (DemandePretType/DemandePret) verifie le format
     * et la coherence des dates ; le controleur verifie la regle metier RG-4 (>= 1 exemplaire
     * libre). L'emprunteur est l'utilisateur connecte (jamais un identifiant transmis par le
     * client : anti-usurpation). Une DEMANDE ne bloque rien (seuls les prets VALIDE reservent) ;
     * l'arbitrage des demandes concurrentes se fera a la validation sous verrou (US-3.4).
     */
    #[Route('/pret/demander/{id}', name: 'app_pret_demander', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function demander(Request $request, Materiel $materiel, ExemplaireRepository $exemplaires, EntityManagerInterface $em): Response
    {
        $demande = new DemandePret();
        $form = $this->createForm(DemandePretType::class, $demande, [
            'action' => $this->generateUrl('app_pret_demander', ['id' => $materiel->getId()]),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            foreach ($form->getErrors(true) as $erreur) {
                $this->addFlash('danger', $erreur->getMessage());
            }

            return $this->redirectToRoute('app_catalogue_show', ['id' => $materiel->getId()]);
        }

        // Les contraintes NotNull garantissent des dates non nulles apres validation.
        \assert(null !== $demande->debut && null !== $demande->fin);

        // RG-4 : au moins un exemplaire libre sur la periode ?
        $exemplaire = $exemplaires->trouverUnLibreSurPeriode($materiel, $demande->debut, $demande->fin);
        if (null === $exemplaire) {
            $this->addFlash('warning', 'Aucun exemplaire disponible sur cette periode.');

            return $this->redirectToRoute('app_catalogue_show', ['id' => $materiel->getId()]);
        }

        $utilisateur = $this->getUser();
        \assert($utilisateur instanceof Utilisateur);

        // Statut DEMANDE et date_demande sont poses par le constructeur de Pret.
        $pret = new Pret();
        $pret->setExemplaire($exemplaire)
            ->setEmprunteur($utilisateur)
            ->setDateDebut($demande->debut)
            ->setDateFin($demande->fin);

        $em->persist($pret);
        $em->flush();

        $this->addFlash('success', 'Votre demande de pret a ete enregistree.');

        return $this->redirectToRoute('app_catalogue_show', ['id' => $materiel->getId()]);
    }

    #[Route('/pret/mes-prets', name: 'app_pret_mes_prets', methods: ['GET'])]
    public function mesPrets(PretRepository $prets): Response
    {
        $utilisateur = $this->getUser();
        \assert($utilisateur instanceof Utilisateur);

        return $this->render('pret/mes_prets.html.twig', [
            'prets' => $prets->findByEmprunteur($utilisateur),
        ]);
    }

    #[Route('/pret/mes-prets/{id}/annuler', name: 'app_pret_annuler', methods: ['POST'])]
    #[IsGranted(PretVoter::ANNULER, subject: 'pret')]
    public function annuler(Request $request, Pret $pret, PretService $service): Response
    {
        if (!$this->isCsrfTokenValid('annuler' . $pret->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide.');

            return $this->redirectToRoute('app_pret_mes_prets');
        }

        $utilisateur = $this->getUser();
        \assert($utilisateur instanceof Utilisateur);

        $service->annuler($pret, $utilisateur);
        $this->addFlash('success', 'Demande annulee.');

        return $this->redirectToRoute('app_pret_mes_prets');
    }
}
