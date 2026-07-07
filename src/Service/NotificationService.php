<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Pret;
use App\Repository\UtilisateurRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envoi d'emails transactionnels (US-4.1). Brique generique : construit un email a partir d'un
 * template Twig et le remet au mailer, qui le depose dans la file Messenger (routing async deja
 * en place) — l'envoi reel se fait hors du cycle HTTP, par le worker.
 *
 * Deux garde-fous transverses :
 *  - Redirection DEV : si APP_MAILER_REDIRECT_TO est definie, tout le courrier part vers cette
 *    unique adresse (le vrai destinataire est rappele dans le sujet). Evite d'ecrire a de vrais
 *    utilisateurs hors production.
 *  - Journalisation RGPD : on ne loggue jamais l'adresse en clair, seulement un hash partiel.
 */
final readonly class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UtilisateurRepository $utilisateurs,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        #[Autowire('%env(APP_NOTIFICATION_FROM)%')]
        private string $expediteur,
        #[Autowire('%env(APP_NOTIFICATION_REPLY_TO)%')]
        private string $replyTo,
        #[Autowire('%env(APP_ENVIRONMENT_LABEL)%')]
        private string $environmentLabel,
        #[Autowire('%env(default::APP_MAILER_REDIRECT_TO)%')]
        private ?string $redirectionDev = null,
    ) {
    }

    /**
     * Construit et envoie un email a partir d'un template Twig.
     *
     * @param array<string, mixed> $contexte
     */
    public function envoyer(string $destinataire, string $sujet, string $template, array $contexte = []): void
    {
        $destinataireReel = $destinataire;
        $sujetFinal = $sujet;

        // Redirection de securite hors production.
        if (null !== $this->redirectionDev && '' !== $this->redirectionDev) {
            $sujetFinal = sprintf('[%s -> %s] %s', $this->environmentLabel, $destinataire, $sujet);
            $destinataireReel = $this->redirectionDev;
        }

        $email = (new TemplatedEmail())
            ->from($this->expediteur)
            ->replyTo($this->replyTo)
            ->to($destinataireReel)
            ->subject($sujetFinal)
            ->htmlTemplate($template)
            ->context($contexte);

        try {
            $this->mailer->send($email);
            $this->logger->info('Notification email deposee.', [
                'destinataire_hash' => substr(hash('sha256', $destinataire), 0, 8),
                'template'          => $template,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Echec de depot d\'une notification email.', [
                'destinataire_hash' => substr(hash('sha256', $destinataire), 0, 8),
                'template'          => $template,
                'erreur'            => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Genere une URL absolue (pour les liens cliquables dans les emails, y compris depuis le
     * worker CLI qui n'a pas de contexte HTTP — repose sur framework.router.default_uri).
     *
     * @param array<string, mixed> $parametres
     */
    public function genererLienAbsolu(string $route, array $parametres = []): string
    {
        return $this->urlGenerator->generate($route, $parametres, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Notifie les gestionnaires qu'une nouvelle demande de pret attend leur validation (US-3.3).
     * Email de service (toujours envoye). Un email par gestionnaire actif.
     */
    public function notifierDemandeCreee(Pret $pret): void
    {
        $lien = $this->genererLienAbsolu('app_gestion_prets');
        foreach ($this->utilisateurs->findGestionnaires() as $gestionnaire) {
            $this->envoyer(
                $gestionnaire->getEmail(),
                'Nouvelle demande de pret a traiter',
                'emails/demande_creee.html.twig',
                ['pret' => $pret, 'lien' => $lien],
            );
        }
    }

    /**
     * Notifie l'emprunteur que son pret est valide (US-3.4). Email de service.
     */
    public function notifierValidation(Pret $pret): void
    {
        $this->envoyer(
            $pret->getEmprunteur()->getEmail(),
            'Votre demande de pret est validee',
            'emails/validation.html.twig',
            ['pret' => $pret, 'lien' => $this->genererLienAbsolu('app_pret_mes_prets')],
        );
    }

    /**
     * Notifie l'emprunteur que son pret est refuse, avec le motif (US-3.4). Email de service.
     */
    public function notifierRefus(Pret $pret): void
    {
        $this->envoyer(
            $pret->getEmprunteur()->getEmail(),
            'Votre demande de pret a ete refusee',
            'emails/refus.html.twig',
            ['pret' => $pret, 'lien' => $this->genererLienAbsolu('app_pret_mes_prets')],
        );
    }

    /**
     * Notifie l'emprunteur que le retour de son pret est enregistre (US-3.5). Email de service.
     */
    public function notifierRetour(Pret $pret): void
    {
        $this->envoyer(
            $pret->getEmprunteur()->getEmail(),
            'Retour de pret enregistre',
            'emails/retour.html.twig',
            ['pret' => $pret],
        );
    }

    /**
     * Rappelle a l'emprunteur que son pret arrive a echeance le lendemain (US-4.3).
     * Email de CONFORT : soumis a l'opt-out emailRappel de l'emprunteur (RGPD art. 6.1.b).
     *
     * @return bool true si l'email a ete mis en file, false s'il a ete supprime par la preference
     */
    public function notifierRappelEcheance(Pret $pret): bool
    {
        $emprunteur = $pret->getEmprunteur();
        if (!$emprunteur->isEmailRappel()) {
            // L'emprunteur a desactive les rappels de confort : on n'envoie pas.
            return false;
        }

        $this->envoyer(
            $emprunteur->getEmail(),
            'Votre pret arrive a echeance demain',
            'emails/rappel_echeance.html.twig',
            ['pret' => $pret, 'lien' => $this->genererLienAbsolu('app_pret_mes_prets')],
        );

        return true;
    }

    /**
     * Alerte l'emprunteur que son pret est en retard (US-4.3).
     * Email NECESSAIRE au service (interet legitime RGPD art. 6.1.f, recuperer le materiel) :
     * toujours envoye, independamment de la preference emailRappel.
     */
    public function notifierRetard(Pret $pret): void
    {
        $this->envoyer(
            $pret->getEmprunteur()->getEmail(),
            'Votre pret est en retard',
            'emails/retard.html.twig',
            ['pret' => $pret, 'lien' => $this->genererLienAbsolu('app_pret_mes_prets')],
        );
    }
}
