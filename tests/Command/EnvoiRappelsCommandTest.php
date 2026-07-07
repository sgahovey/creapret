<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\EnvoiRappelsCommand;
use App\Entity\Pret;
use App\Repository\PretRepository;
use App\Service\DateFormatterService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class EnvoiRappelsCommandTest extends TestCase
{
    private MockObject&PretRepository $prets;
    private MockObject&NotificationService $notifications;
    private MockObject&EntityManagerInterface $em;
    private MockObject&LoggerInterface $logger;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->prets = $this->createMock(PretRepository::class);
        $this->notifications = $this->createMock(NotificationService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $command = new EnvoiRappelsCommand(
            $this->prets,
            $this->notifications,
            $this->em,
            $this->logger,
            new DateFormatterService(),
        );

        $application = new Application();
        $application->addCommand($command);
        $this->tester = new CommandTester($application->find('app:prets:rappels'));
    }

    /** @return MockObject&Pret */
    private function pretMock(int $id): MockObject
    {
        $pret = $this->createMock(Pret::class);
        $pret->method('getId')->willReturn($id);

        return $pret;
    }

    /** Sortie normalisee (le bloc success() de SymfonyStyle retourne a la ligne selon la largeur). */
    private function sortieNormalisee(): string
    {
        return (string) preg_replace('/\s+/', ' ', $this->tester->getDisplay());
    }

    public function test_nominal_envoie_rappels_et_retards_et_pose_les_marqueurs(): void
    {
        $rappel1 = $this->pretMock(1);
        $rappel2 = $this->pretMock(2);
        $retard1 = $this->pretMock(10);

        $this->prets->expects(self::once())->method('findPourRappelEcheance')->willReturn([$rappel1, $rappel2]);
        $this->prets->expects(self::once())->method('findEnRetard')->willReturn([$retard1]);

        $this->notifications->expects(self::exactly(2))->method('notifierRappelEcheance')->willReturn(true);
        $this->notifications->expects(self::once())->method('notifierRetard')->with($retard1);

        $rappel1->expects(self::once())->method('setRappelEcheanceEnvoyeAt');
        $rappel2->expects(self::once())->method('setRappelEcheanceEnvoyeAt');
        $retard1->expects(self::once())->method('setRetardNotifieAt');

        $this->logger->expects(self::never())->method('error');
        $this->em->expects(self::once())->method('flush');

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $sortie = $this->sortieNormalisee();
        self::assertStringContainsString('2 rappel(s) envoye(s)', $sortie);
        self::assertStringContainsString('0 ignore(s)', $sortie);
        self::assertStringContainsString('1 alerte(s) de retard', $sortie);
        self::assertStringContainsString('0 erreur(s)', $sortie);
    }

    public function test_opt_out_n_envoie_pas_et_ne_marque_pas_le_rappel(): void
    {
        $rappel = $this->pretMock(1);
        $this->prets->expects(self::once())->method('findPourRappelEcheance')->willReturn([$rappel]);
        $this->prets->expects(self::once())->method('findEnRetard')->willReturn([]);

        $this->notifications->expects(self::once())->method('notifierRappelEcheance')->willReturn(false);
        $this->notifications->expects(self::never())->method('notifierRetard');
        $rappel->expects(self::never())->method('setRappelEcheanceEnvoyeAt');
        $this->logger->expects(self::never())->method('error');
        $this->em->expects(self::once())->method('flush');

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $sortie = $this->sortieNormalisee();
        self::assertStringContainsString('0 rappel(s) envoye(s)', $sortie);
        self::assertStringContainsString('1 ignore(s) (opt-out)', $sortie);
    }

    public function test_resilience_un_echec_est_compte_sans_bloquer_le_batch(): void
    {
        $ok = $this->pretMock(10);
        $ko = $this->pretMock(20);
        $this->prets->expects(self::once())->method('findPourRappelEcheance')->willReturn([]);
        $this->prets->expects(self::once())->method('findEnRetard')->willReturn([$ok, $ko]);

        $this->notifications->expects(self::never())->method('notifierRappelEcheance');
        $this->notifications->expects(self::exactly(2))->method('notifierRetard')->willReturnCallback(
            function (Pret $pret): void {
                if (20 === $pret->getId()) {
                    throw new \RuntimeException('SMTP indisponible');
                }
            },
        );
        $ok->expects(self::once())->method('setRetardNotifieAt');
        $ko->expects(self::never())->method('setRetardNotifieAt');
        $this->logger->expects(self::once())->method('error');
        $this->em->expects(self::once())->method('flush');

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $sortie = $this->sortieNormalisee();
        self::assertStringContainsString('1 alerte(s) de retard', $sortie);
        self::assertStringContainsString('1 erreur(s)', $sortie);
    }

    public function test_no_op_flush_quand_meme_et_aucun_envoi(): void
    {
        $this->prets->expects(self::once())->method('findPourRappelEcheance')->willReturn([]);
        $this->prets->expects(self::once())->method('findEnRetard')->willReturn([]);
        $this->notifications->expects(self::never())->method('notifierRappelEcheance');
        $this->notifications->expects(self::never())->method('notifierRetard');
        $this->logger->expects(self::never())->method('error');
        $this->em->expects(self::once())->method('flush');

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertStringContainsString('0 rappel(s) envoye(s)', $this->sortieNormalisee());
    }

    public function test_resilience_un_echec_de_rappel_est_compte_sans_bloquer(): void
    {
        $ok = $this->pretMock(1);
        $ko = $this->pretMock(2);
        $this->prets->expects(self::once())->method('findPourRappelEcheance')->willReturn([$ok, $ko]);
        $this->prets->expects(self::once())->method('findEnRetard')->willReturn([]);

        // Le 2e rappel leve une exception ; le 1er passe (preference active).
        $this->notifications->expects(self::exactly(2))->method('notifierRappelEcheance')->willReturnCallback(
            function (Pret $pret): bool {
                if (2 === $pret->getId()) {
                    throw new \RuntimeException('SMTP indisponible');
                }

                return true;
            },
        );
        $this->notifications->expects(self::never())->method('notifierRetard');
        $ok->expects(self::once())->method('setRappelEcheanceEnvoyeAt');
        $ko->expects(self::never())->method('setRappelEcheanceEnvoyeAt');
        $this->logger->expects(self::once())->method('error');
        $this->em->expects(self::once())->method('flush');

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $sortie = $this->sortieNormalisee();
        self::assertStringContainsString('1 rappel(s) envoye(s)', $sortie);
        self::assertStringContainsString('1 erreur(s)', $sortie);
    }
}
