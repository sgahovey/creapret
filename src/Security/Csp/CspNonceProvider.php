<?php

declare(strict_types=1);

namespace App\Security\Csp;

/**
 * Fournit un nonce CSP unique par requete (US-6.3).
 *
 * Le nonce est genere paresseusement a la premiere demande puis memoise : le meme service (donc le
 * meme nonce) est partage par autowiring entre l'extension Twig -- qui l'insere sur les scripts
 * inline -- et le listener qui pose l'en-tete Content-Security-Policy. En PHP-FPM le conteneur est
 * recree a chaque requete, garantissant un nonce distinct par requete sans etat supplementaire.
 */
final class CspNonceProvider
{
    private ?string $nonce = null;

    public function getNonce(): string
    {
        return $this->nonce ??= base64_encode(random_bytes(16));
    }
}
