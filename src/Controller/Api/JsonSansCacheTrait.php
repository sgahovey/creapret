<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Fournit une reponse JSON non mise en cache (donnees de pret = privees, RGPD).
 * setPrivate + no-store : ni les caches partages ni le navigateur ne conservent la reponse.
 */
trait JsonSansCacheTrait
{
    /**
     * @param array<int|string, mixed> $donnees
     */
    private function jsonSansCache(array $donnees): JsonResponse
    {
        $reponse = new JsonResponse($donnees);
        $reponse->setPrivate();
        $reponse->headers->addCacheControlDirective('no-store');
        $reponse->headers->addCacheControlDirective('no-cache');

        return $reponse;
    }
}
