<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifie que les 3 pages legales (US-5.4) sont accessibles SANS authentification et
 * repondent en succes (200). Ce sont des regles PUBLIC_ACCESS dans security.yaml ;
 * aucune entite creee, donc pas de tearDown.
 */
final class LegalPagesTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pagesLegalesProvider(): iterable
    {
        yield 'mentions legales' => ['/mentions-legales', 'Mentions légales'];
        yield 'confidentialite' => ['/confidentialite', 'Politique de confidentialité'];
        yield 'accessibilite' => ['/accessibilite', 'accessibilité'];
    }

    #[DataProvider('pagesLegalesProvider')]
    public function test_page_legale_accessible_sans_authentification(string $url, string $titre): void
    {
        $client = static::createClient();

        // Aucun loginUser() : on verifie justement l'acces public.
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $titre);
    }
}
