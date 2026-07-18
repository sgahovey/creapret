<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\StatutPret;
use App\Service\PretCalendarSerializer;
use PHPUnit\Framework\TestCase;

final class PretCalendarSerializerTest extends TestCase
{
    private PretCalendarSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new PretCalendarSerializer();
    }

    private function pret(): Pret
    {
        $materiel = (new Materiel())->setNom('Videoprojecteur');
        $exemplaire = (new Exemplaire())->setNumeroInventaire('VP-001')->setMateriel($materiel);
        $emprunteur = (new Utilisateur())->setNom('Dupont')->setPrenom('Marie');

        return (new Pret())
            ->setExemplaire($exemplaire)
            ->setEmprunteur($emprunteur)
            ->setDateDebut(new \DateTimeImmutable('2026-09-10 08:00:00'))
            ->setDateFin(new \DateTimeImmutable('2026-09-15 17:00:00'))
            ->setStatut(StatutPret::VALIDE);
    }

    public function test_transforme_un_pret_en_evenement_fullcalendar(): void
    {
        $evenements = $this->serializer->toCalendarEvents([$this->pret()]);

        self::assertCount(1, $evenements);
        $e = $evenements[0];
        self::assertSame('Videoprojecteur (VP-001)', $e['title']);
        self::assertStringStartsWith('2026-09-10', $e['start']);
        self::assertStringStartsWith('2026-09-15', $e['end']);
        self::assertArrayHasKey('color', $e);
        self::assertArrayHasKey('extendedProps', $e);
    }

    public function test_le_titre_ne_contient_aucune_donnee_nominative(): void
    {
        // Minimisation RGPD : le titre affiche sur la grille ne doit pas exposer l'emprunteur.
        $e = $this->serializer->toCalendarEvents([$this->pret()])[0];

        self::assertStringNotContainsString('Dupont', $e['title']);
        self::assertStringNotContainsString('Marie', $e['title']);
    }

    public function test_dates_au_format_atom(): void
    {
        $e = $this->serializer->toCalendarEvents([$this->pret()])[0];

        // Format ATOM attendu par FullCalendar (ex. 2026-09-10T08:00:00+00:00).
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $e['start']);
    }
}
