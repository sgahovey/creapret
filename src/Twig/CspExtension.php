<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\Csp\CspNonceProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le nonce CSP aux templates via la fonction Twig csp_nonce() (US-6.3), pour nonce-r les
 * scripts inline (notamment la carte d'import AssetMapper) et permettre un script-src strict sans
 * 'unsafe-inline'.
 */
final class CspExtension extends AbstractExtension
{
    public function __construct(
        private readonly CspNonceProvider $nonceProvider,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->getNonce(...)),
        ];
    }

    /**
     * L'argument $usage (ex. 'script') est accepte pour rester compatible avec l'appel
     * csp_nonce('script') d'importmap(), mais ignore : un seul nonce par requete couvre tous les usages.
     */
    public function getNonce(string $usage = 'script'): string
    {
        return $this->nonceProvider->getNonce();
    }
}
