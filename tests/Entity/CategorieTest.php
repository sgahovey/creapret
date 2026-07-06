<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Categorie;
use App\Entity\Materiel;
use PHPUnit\Framework\TestCase;

final class CategorieTest extends TestCase
{
    public function test_accesseurs(): void
    {
        $c = (new Categorie())->setNom('Informatique')->setDescription('Postes et peripheriques');

        self::assertSame('Informatique', $c->getNom());
        self::assertSame('Postes et peripheriques', $c->getDescription());
        self::assertNull($c->getId());
        self::assertCount(0, $c->getMateriels());
    }

    public function test_ajout_materiel_synchronise_la_relation(): void
    {
        $c = (new Categorie())->setNom('Audiovisuel');
        $m = (new Materiel())->setNom('Videoprojecteur Epson');

        $c->addMateriel($m);

        self::assertCount(1, $c->getMateriels());
        self::assertSame($c, $m->getCategorie());
    }

    public function test_retrait_materiel(): void
    {
        $c = (new Categorie())->setNom('Mesure');
        $m = (new Materiel())->setNom('Multimetre');
        $c->addMateriel($m);

        $c->removeMateriel($m);

        self::assertCount(0, $c->getMateriels());
    }
}
