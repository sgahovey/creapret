<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\PretRepository;
use App\Service\PretCalendarSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API du calendrier d'occupation du materiel (US-4.4). Reservee aux gestionnaires.
 * Alimente FullCalendar, qui envoie automatiquement les bornes start/end en query.
 */
#[Route('/gestion/api/calendrier')]
#[IsGranted('ROLE_GESTIONNAIRE')]
final class CalendrierApiController extends AbstractController
{
    use JsonSansCacheTrait;

    #[Route('/prets', name: 'api_calendrier_prets', methods: ['GET'])]
    public function prets(Request $request, PretRepository $prets, PretCalendarSerializer $serializer): JsonResponse
    {
        $debutBrut = $request->query->getString('start');
        $finBrut = $request->query->getString('end');

        if ('' === $debutBrut || '' === $finBrut) {
            // Sans fenetre, on ne renvoie aucun evenement (FullCalendar fournit toujours start/end).
            return $this->jsonSansCache([]);
        }

        try {
            $debut = new \DateTimeImmutable($debutBrut);
            $fin = new \DateTimeImmutable($finBrut);
        } catch (\Exception) {
            return $this->json(['erreur' => 'Dates invalides.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->jsonSansCache($serializer->toCalendarEvents($prets->findPourCalendrier($debut, $fin)));
    }
}
