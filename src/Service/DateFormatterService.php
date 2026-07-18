<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Formatage des dates en francais, fuseau de La Reunion, pour les emails et libelles.
 * Service sans etat : convertit toute date (immuable ou non) sans la muter.
 */
final readonly class DateFormatterService
{
    private const string TIMEZONE = 'Indian/Reunion';

    /** Ex. "26/05/2026 a 14h30" (pour les sujets d'email). */
    public function pourSujetEmail(\DateTimeInterface $date): string
    {
        $d = $this->convertir($date);

        return $d->format('d/m/Y') . ' a ' . $d->format('H\hi');
    }

    /** Ex. "26/05/2026". */
    public function pourDate(\DateTimeInterface $date): string
    {
        return $this->convertir($date)->format('d/m/Y');
    }

    /** Ex. "14:30". */
    public function pourHeure(\DateTimeInterface $date): string
    {
        return $this->convertir($date)->format('H:i');
    }

    /** Ex. "14h30". */
    public function pourHeureCompacte(\DateTimeInterface $date): string
    {
        return $this->convertir($date)->format('H\hi');
    }

    private function convertir(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new \DateTimeZone(self::TIMEZONE));
    }
}
