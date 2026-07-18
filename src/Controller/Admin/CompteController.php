<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Enum\TypeActionJournal;
use App\Form\UtilisateurAdminType;
use App\Repository\UtilisateurRepository;
use App\Security\Voter\UtilisateurVoter;
use App\Service\JournalAdminService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des comptes par le super-administrateur (US-6.2, BF-13).
 *
 * Protege au niveau classe par ROLE_SUPER_ADMIN (defense en profondeur : access_control ^/admin est
 * la 2e barriere). Aucune donnee sensible n'est exposee en vue (le hash de mot de passe ne quitte
 * jamais l'entite). Chaque action mutante est tracee dans le journal d'administration, dans la MEME
 * transaction que l'action, pour qu'une trace ne puisse exister sans son action ni l'inverse.
 */
#[Route('/admin/comptes')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class CompteController extends AbstractController
{
    private const int COMPTES_PAR_PAGE = 20;

    public function __construct(
        private readonly UtilisateurRepository $utilisateurs,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly JournalAdminService $journal,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'app_admin_comptes', methods: ['GET'])]
    public function liste(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $recherche = trim($request->query->getString('recherche'));

        $comptes = $this->utilisateurs->findAllPourAdmin(
            $page,
            self::COMPTES_PAR_PAGE,
            '' !== $recherche ? $recherche : null,
        );
        $total = count($comptes);

        return $this->render('admin/compte/liste.html.twig', [
            'comptes'   => $comptes,
            'page'      => $page,
            'nbPages'   => max(1, (int) ceil($total / self::COMPTES_PAR_PAGE)),
            'total'     => $total,
            'recherche' => $recherche,
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_compte_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request): Response
    {
        $compte = new Utilisateur();
        $formulaire = $this->createForm(UtilisateurAdminType::class, $compte, ['avec_mot_de_passe' => true]);
        $formulaire->handleRequest($request);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            $motDePasse = (string) $formulaire->get('plainPassword')->getData();
            $compte->setMotDePasseHash($this->hasher->hashPassword($compte, $motDePasse));

            $administrateur = $this->getUser();
            \assert($administrateur instanceof Utilisateur);

            // Compte cree par un tiers : aucun consentement. consentir() N'EST PAS appele et
            // dateConsentement/versionCgu restent nuls -- y inscrire une preuve serait une falsification.
            // Action + trace dans une meme transaction. La cibleId n'existe qu'apres un 1er flush : on
            // persiste puis flush le compte, on enregistre la trace, puis on flush a nouveau.
            $this->em->wrapInTransaction(function () use ($compte, $administrateur): void {
                $this->em->persist($compte);
                $this->em->flush();

                $this->journal->enregistrer(
                    TypeActionJournal::COMPTE_CREATION,
                    $administrateur,
                    $compte,
                    $compte->getEmail(),
                );
                $this->em->flush();
            });

            $this->logger->info('Compte cree par un administrateur', [
                'admin_id' => $administrateur->getId(),
                'cible_id' => $compte->getId(),
            ]);
            $this->addFlash('success', 'Le compte a été créé.');

            return $this->redirectToRoute('app_admin_comptes');
        }

        return $this->render('admin/compte/nouveau.html.twig', [
            'formulaire' => $formulaire,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_compte_modifier', methods: ['GET', 'POST'])]
    public function modifier(Utilisateur $compte, Request $request): Response
    {
        $this->denyAccessUnlessGranted(UtilisateurVoter::MODIFIER, $compte);

        $roleAvant = $compte->getRole();
        $formulaire = $this->createForm(UtilisateurAdminType::class, $compte, ['avec_mot_de_passe' => false]);
        $formulaire->handleRequest($request);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            $roleApres = $compte->getRole();
            $roleChange = $roleApres !== $roleAvant;

            // Un changement de role est plus sensible qu'une simple edition : il exige la garde dediee
            // (anti-soi) en plus de MODIFIER deja verifie a l'entree.
            if ($roleChange) {
                $this->denyAccessUnlessGranted(UtilisateurVoter::CHANGER_ROLE, $compte);
            }

            $administrateur = $this->getUser();
            \assert($administrateur instanceof Utilisateur);

            // UNE SEULE entree de journal, la plus significative (un changement de role prime sur une
            // simple modification) : deux traces pour une meme operation rendraient le journal illisible.
            // Trace persistee avant le flush : action et journal commites ensemble (service persist-only).
            $this->em->wrapInTransaction(function () use ($compte, $administrateur, $roleChange, $roleAvant, $roleApres): void {
                if ($roleChange) {
                    $this->journal->enregistrer(
                        TypeActionJournal::COMPTE_CHANGEMENT_ROLE,
                        $administrateur,
                        $compte,
                        sprintf('%s -> %s', $roleAvant->libelle(), $roleApres->libelle()),
                    );
                } else {
                    $this->journal->enregistrer(TypeActionJournal::COMPTE_MODIFICATION, $administrateur, $compte);
                }
                $this->em->flush();
            });

            $this->logger->info('Compte modifie par un administrateur', [
                'admin_id'    => $administrateur->getId(),
                'cible_id'    => $compte->getId(),
                'role_change' => $roleChange,
            ]);
            $this->addFlash('success', 'Le compte a été modifié.');

            return $this->redirectToRoute('app_admin_comptes');
        }

        return $this->render('admin/compte/modifier.html.twig', [
            'formulaire' => $formulaire,
            'compte'     => $compte,
        ]);
    }

    #[Route('/{id}/activation', name: 'app_admin_compte_activation', methods: ['POST'])]
    public function basculerActivation(Utilisateur $compte, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('activation' . $compte->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_comptes');
        }

        $desactivation = $compte->isEstActif();

        // Ordre des gardes STRICT.
        // (a) Invariant GLOBAL d'abord : ne jamais desactiver le dernier super-administrateur actif.
        //     Il porte sur un etat global (comptage), pas sur la relation acteur/cible ; place avant le
        //     Voter, il donne le message metier le plus clair.
        if ($desactivation
            && Role::SUPER_ADMIN === $compte->getRole()
            && $this->utilisateurs->countSuperAdminsActifs() <= 1) {
            $this->addFlash('danger', 'Impossible de désactiver le dernier super-administrateur actif.');

            return $this->redirectToRoute('app_admin_comptes');
        }

        // (b) Puis la garde anti-soi (relation acteur/cible), portee par le Voter.
        $this->denyAccessUnlessGranted(
            $desactivation ? UtilisateurVoter::DESACTIVER : UtilisateurVoter::ACTIVER,
            $compte,
        );

        $administrateur = $this->getUser();
        \assert($administrateur instanceof Utilisateur);

        $this->em->wrapInTransaction(function () use ($compte, $administrateur, $desactivation): void {
            $compte->setEstActif(!$desactivation);
            $this->journal->enregistrer(
                $desactivation ? TypeActionJournal::COMPTE_DESACTIVATION : TypeActionJournal::COMPTE_ACTIVATION,
                $administrateur,
                $compte,
            );
            $this->em->flush();
        });

        $this->logger->info('Activation de compte basculee', [
            'admin_id'  => $administrateur->getId(),
            'cible_id'  => $compte->getId(),
            'est_actif' => $compte->isEstActif(),
        ]);
        $this->addFlash('success', $desactivation ? 'Le compte a été désactivé.' : 'Le compte a été réactivé.');

        return $this->redirectToRoute('app_admin_comptes');
    }
}
