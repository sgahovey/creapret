<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifie les en-tetes de securite poses par SecuriteEnTetesListener (US-6.3, OWASP A05). En
 * environnement de test le listener s'applique (seul « dev » est exclu) : CSP a nonce sur le HTML,
 * nonce de l'en-tete identique a celui de la carte d'import inline, en-tetes complementaires poses,
 * et ABSENCE de CSP sur le JSON de l'API.
 */
final class EnTetesSecuriteTest extends WebTestCase
{
    private const MARQUEUR = 'entetes.';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        parent::tearDown();
    }

    public function test_page_html_porte_csp_a_nonce_coherent_et_en_tetes(): void
    {
        $this->client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        $reponse = $this->client->getResponse();
        $csp = (string) $reponse->headers->get('Content-Security-Policy');

        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("script-src 'self' 'nonce-", $csp);
        self::assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);

        // Nonce de l'en-tete == nonce du script inline (carte d'import).
        self::assertSame(1, preg_match("/script-src 'self' 'nonce-([A-Za-z0-9+\/=]+)'/", $csp, $m));
        self::assertStringContainsString('nonce="' . $m[1] . '"', (string) $reponse->getContent());

        // En-tetes complementaires.
        self::assertSame('nosniff', $reponse->headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $reponse->headers->get('Referrer-Policy'));
        self::assertSame('DENY', $reponse->headers->get('X-Frame-Options'));
    }

    public function test_json_api_ne_porte_pas_de_csp(): void
    {
        $this->client->loginUser($this->gestionnaire());

        $this->client->request('GET', '/gestion/api/calendrier/prets?start=2026-09-01&end=2026-09-30');

        self::assertResponseIsSuccessful();
        $reponse = $this->client->getResponse();
        self::assertStringContainsString('application/json', (string) $reponse->headers->get('Content-Type'));
        self::assertFalse(
            $reponse->headers->has('Content-Security-Policy'),
            'Le JSON de l\'API ne doit pas porter de Content-Security-Policy (exclusion HTML-only).',
        );
    }

    private function gestionnaire(): Utilisateur
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $u = (new Utilisateur())
            ->setEmail(self::MARQUEUR . uniqid() . '@creapret.local')
            ->setNom('Entetes')->setPrenom('Gest')
            ->setRole(Role::GESTIONNAIRE)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'Motdepasse1!'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($u);
        $em->flush();

        return $u;
    }
}
