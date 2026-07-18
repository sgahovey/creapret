<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationServiceTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    private function service(?string $redirectionDev = null): NotificationService
    {
        $c = static::getContainer();

        /** @var UtilisateurRepository $utilisateurs */
        $utilisateurs = $c->get(EntityManagerInterface::class)->getRepository(Utilisateur::class);

        return new NotificationService(
            $c->get(MailerInterface::class),
            $utilisateurs,
            $c->get(UrlGeneratorInterface::class),
            $c->get(LoggerInterface::class),
            'noreply@creapret.local',
            'support@creapret.local',
            'test',
            $redirectionDev,
        );
    }

    public function test_envoi_nominal_met_un_email_avec_le_bon_destinataire_et_sujet(): void
    {
        $this->service()->envoyer('destinataire@creapret.local', 'Bienvenue', 'emails/message.html.twig', [
            'titre'   => 'Bonjour',
            'contenu' => 'Ceci est un message de test.',
        ]);

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Bienvenue', $email->getSubject());
        self::assertEmailAddressContains($email, 'To', 'destinataire@creapret.local');
        self::assertEmailHtmlBodyContains($email, 'Ceci est un message de test.');
    }

    public function test_redirection_dev_reroute_vers_l_adresse_de_test(): void
    {
        $this->service(redirectionDev: 'boite-de-test@creapret.local')
            ->envoyer('vrai-destinataire@creapret.local', 'Sujet', 'emails/message.html.twig', [
                'titre'   => 'T',
                'contenu' => 'C',
            ]);

        $email = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $email);
        // L'email part vers l'adresse de test, pas vers le vrai destinataire.
        self::assertEmailAddressContains($email, 'To', 'boite-de-test@creapret.local');
        // Le vrai destinataire est rappele dans le sujet.
        self::assertStringContainsString('vrai-destinataire@creapret.local', (string) $email->getSubject());
    }

    public function test_generer_lien_absolu_produit_une_url_complete(): void
    {
        $url = $this->service()->genererLienAbsolu('app_pret_mes_prets');

        self::assertStringStartsWith('http', $url);
        self::assertStringContainsString('/pret/mes-prets', $url);
    }
}
