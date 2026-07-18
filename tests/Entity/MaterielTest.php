<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Categorie;
use App\Entity\Materiel;
use PHPUnit\Framework\TestCase;

final class MaterielTest extends TestCase
{
    public function test_accesseurs(): void
    {
        $c = (new Categorie())->setNom('Informatique');
        $m = (new Materiel())
            ->setNom('Dell Latitude 5540')
            ->setDescription('Ordinateur portable 15 pouces')
            ->setMarque('Dell')
            ->setModele('Latitude 5540')
            ->setReference('LAT-5540')
            ->setCategorie($c);

        self::assertSame('Dell Latitude 5540', $m->getNom());
        self::assertSame('Ordinateur portable 15 pouces', $m->getDescription());
        self::assertSame('Dell', $m->getMarque());
        self::assertSame('Latitude 5540', $m->getModele());
        self::assertSame('LAT-5540', $m->getReference());
        self::assertSame($c, $m->getCategorie());
        self::assertNull($m->getId());
    }

    public function test_champs_optionnels_null_par_defaut(): void
    {
        $m = (new Materiel())->setNom('Cable HDMI');

        self::assertNull($m->getDescription());
        self::assertNull($m->getMarque());
        self::assertNull($m->getModele());
        self::assertNull($m->getReference());
    }
}
