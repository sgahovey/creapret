<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\Csp\CspNonceProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pose les en-tetes de securite HTTP sur les reponses HTML (US-6.3 ; OWASP A05, ANSSI).
 *
 * UNIQUE endroit ou ces en-tetes sont definis pour l'application :
 *  - Content-Security-Policy a nonce (script-src strict, sans 'unsafe-inline' ni 'unsafe-eval') ;
 *    le meme CspNonceProvider (autowiring) alimente les scripts inline nonce-es des templates.
 *  - X-Content-Type-Options, Referrer-Policy, X-Frame-Options.
 *  - Strict-Transport-Security seulement sur requete securisee (HSTS est ignore en clair, et le poser
 *    inconditionnellement ferait doublon le jour ou un reverse-proxy le posera aussi).
 *
 * Ne s'applique pas en dev (barre de debogage), ni au JSON de l'API (reponses non-HTML), et n'ecrase
 * jamais un en-tete deja pose. style-src conserve 'unsafe-inline' (cf. DT-9).
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SecuriteEnTetesListener
{
    public function __construct(
        private readonly CspNonceProvider $nonceProvider,
        #[Autowire('%kernel.environment%')]
        private readonly string $environnement,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        // VERIFICATION MANUELLE EN LOCAL : ces en-tetes ne sont volontairement pas poses en dev.
        // Pour verifier la politique (CSP a nonce, etc.) sur son poste, commenter TEMPORAIREMENT la
        // condition « 'dev' === \$this->environnement » ci-dessous (ou lancer en APP_ENV=preprod),
        // puis RETABLIR : la barre de debogage Symfony injecte des <script>/<style> inline non
        // nonce-es que le script-src strict bloquerait, cassant le profiler en dev.
        if (!$event->isMainRequest() || 'dev' === $this->environnement) {
            return;
        }

        $response = $event->getResponse();

        // Reponses HTML uniquement. A kernel.response, une reponse issue de render() n'a pas encore
        // son Content-Type (pose par Response::prepare()) -> on retient text/html par defaut ; les
        // JsonResponse fixent leur Content-Type des la construction et restent donc exclues.
        $contentType = (string) $response->headers->get('Content-Type', 'text/html');
        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        if (!$response->headers->has('Content-Security-Policy')) {
            $nonce = $this->nonceProvider->getNonce();
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "frame-ancestors 'none'",
                "form-action 'self'",
            ]));
        }

        if (!$response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }
        if (!$response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        if (!$response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        if ($event->getRequest()->isSecure() && !$response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
