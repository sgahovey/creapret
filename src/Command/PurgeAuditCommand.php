<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\JournalAdmin;
use App\Repository\JournalAdminRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge les traces d'audit au-dela de la duree de conservation (RGPD, limitation de conservation,
 * CNIL art. 5.1.e).
 *
 * Deux mecanismes d'audit sont purges avec le meme seuil : le journal d'administration applicatif
 * (journal_admin, mappe, purge via son repository) et l'historique des comptes alimente par trigger
 * (historique_utilisateur, non mappe, purge par requete SQL native). Le seuil est configurable en
 * jours (--jours) et un mode simulation (--dry-run) compte sans supprimer.
 */
#[AsCommand(
    name: 'app:audit:purger',
    description: 'Purge les traces d audit (journal et historique) au-dela de la duree de conservation.',
)]
final class PurgeAuditCommand extends Command
{
    private const string TIMEZONE = 'Indian/Reunion';

    public function __construct(
        private readonly JournalAdminRepository $journalAdminRepository,
        private readonly Connection $connexion,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('jours', null, InputOption::VALUE_REQUIRED, 'Duree de conservation en jours', JournalAdmin::DUREE_CONSERVATION_JOURS)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule la purge sans rien supprimer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $jours = (int) $input->getOption('jours');
        $simulation = (bool) $input->getOption('dry-run');

        if ($jours < 1) {
            $io->error('Le nombre de jours doit etre au moins 1.');

            return Command::INVALID;
        }

        $seuil = (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->modify(sprintf('-%d days', $jours));
        $seuilSql = $seuil->format('Y-m-d H:i:s');

        if ($simulation) {
            $journal = $this->journalAdminRepository->compterAvant($seuil);
            $historique = (int) $this->connexion->fetchOne(
                'SELECT COUNT(*) FROM historique_utilisateur WHERE date_modification < :seuil',
                ['seuil' => $seuilSql],
            );
            $io->note(sprintf('Simulation : %d entree(s) de journal et %d entree(s) d historique SERAIENT purgees (anterieures au %s).', $journal, $historique, $seuil->format('d/m/Y')));
            $this->logger->info('Purge audit (simulation)', ['journal' => $journal, 'historique' => $historique, 'seuil' => $seuilSql]);

            return Command::SUCCESS;
        }

        $journal = $this->journalAdminRepository->purgerAvant($seuil);
        $historique = (int) $this->connexion->executeStatement(
            'DELETE FROM historique_utilisateur WHERE date_modification < :seuil',
            ['seuil' => $seuilSql],
        );

        $io->success(sprintf('%d entree(s) de journal et %d entree(s) d historique purgees (anterieures au %s).', $journal, $historique, $seuil->format('d/m/Y')));
        $this->logger->info('Purge audit effectuee', ['journal' => $journal, 'historique' => $historique, 'seuil' => $seuilSql]);

        return Command::SUCCESS;
    }
}
