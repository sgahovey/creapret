<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DateFormatterService;
use PHPUnit\Framework\TestCase;

final class DateFormatterServiceTest extends TestCase
{
    private DateFormatterService $formatter;

    protected function setUp(): void
    {
        $this->formatter = new DateFormatterService();
    }

    public function test_conversion_vers_le_fuseau_de_la_reunion(): void
    {
        // 10:30 UTC = 14:30 a La Reunion (UTC+4).
        $utc = new \DateTimeImmutable('2026-05-26 10:30:00', new \DateTimeZone('UTC'));

        self::assertSame('14:30', $this->formatter->pourHeure($utc));
        self::assertSame('14h30', $this->formatter->pourHeureCompacte($utc));
        self::assertSame('26/05/2026', $this->formatter->pourDate($utc));
        self::assertSame('26/05/2026 a 14h30', $this->formatter->pourSujetEmail($utc));
    }

    public function test_n_altere_pas_la_date_source(): void
    {
        $source = new \DateTimeImmutable('2026-05-26 10:30:00', new \DateTimeZone('UTC'));
        $this->formatter->pourSujetEmail($source);

        // La date source reste en UTC (immuable, non mutee).
        self::assertSame('10:30', $source->format('H:i'));
    }
}
