<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PretRepository;
use App\Service\DateFormatterService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande planifiable (cron) : envoie les rappels d'echeance (la veille de la date de fin) et les
 * alertes de retard (date de fin depassee). Deux passes, idempotentes grace aux marqueurs poses sur
 * chaque pret traite. Concue pour un passage quotidien.
 */
#[AsCommand(
    name: 'app:prets:rappels',
    description: 'Envoie les rappels d\'echeance (J-1) et les alertes de retard des prets.',
)]
final class EnvoiRappelsCommand extends Command
{
    private const string TIMEZONE = 'Indian/Reunion';

    public function __construct(
        private readonly PretRepository $prets,
        private readonly NotificationService $notifications,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly DateFormatterService $dateFormatter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Rappels d\'echeance et alertes de retard');

        // Ancrage explicite du fuseau de La Reunion : un "demain" mal ancre decalerait le rappel.
        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
        $demainDebut = $maintenant->modify('+1 day')->setTime(0, 0, 0);
        $demainFin = $maintenant->modify('+1 day')->setTime(23, 59, 59);

        $rappels = 0;
        $optOut = 0;
        $retards = 0;
        $erreurs = 0;

        // Passe 1 : rappels d'echeance (la veille).
        $io->section(sprintf('Rappels d\'echeance pour le %s', $this->dateFormatter->pourDate($demainDebut)));
        foreach ($this->prets->findPourRappelEcheance($demainDebut, $demainFin) as $pret) {
            try {
                if ($this->notifications->notifierRappelEcheance($pret)) {
                    // Marque uniquement si l'email est reellement parti (l'opt-out laisse le marqueur nul).
                    $pret->setRappelEcheanceEnvoyeAt($maintenant);
                    ++$rappels;
                } else {
                    ++$optOut;
                }
            } catch (\Throwable $e) {
                ++$erreurs;
                $this->logger->error('Echec du rappel d\'echeance (batch).', [
                    'pret_id'   => $pret->getId(),
                    'exception' => $e::class,
                    'message'   => $e->getMessage(),
                ]);
            }
        }

        // Passe 2 : alertes de retard.
        $io->section('Alertes de retard');
        foreach ($this->prets->findEnRetard($maintenant) as $pret) {
            try {
                $this->notifications->notifierRetard($pret);
                $pret->setRetardNotifieAt($maintenant);
                ++$retards;
            } catch (\Throwable $e) {
                ++$erreurs;
                $this->logger->error('Echec de l\'alerte de retard (batch).', [
                    'pret_id'   => $pret->getId(),
                    'exception' => $e::class,
                    'message'   => $e->getMessage(),
                ]);
            }
        }

        // Un seul flush apres les deux passes : les marqueurs sont persistes en une transaction.
        $this->em->flush();

        $io->success(sprintf(
            '%d rappel(s) envoye(s), %d ignore(s) (opt-out), %d alerte(s) de retard, %d erreur(s).',
            $rappels,
            $optOut,
            $retards,
            $erreurs,
        ));

        return Command::SUCCESS;
    }
}
