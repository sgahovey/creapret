<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Enum\EtatExemplaire;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Donnees de reference stables (catalogue) : une categorie, un materiel, trois exemplaires
 * disponibles. Exposees via des references pour que DemoFixtures cree un pret dessus.
 */
final class ReferenceFixtures extends Fixture implements FixtureGroupInterface
{
    public const EXEMPLAIRE_PREFIXE = 'exemplaire-';

    public static function getGroups(): array
    {
        return ['reference'];
    }

    public function load(ObjectManager $manager): void
    {
        $categorie = (new Categorie())
            ->setNom('Video')
            ->setDescription('Materiel de projection et de captation video.');
        $manager->persist($categorie);

        $materiel = (new Materiel())
            ->setNom('Videoprojecteur Epson EB-982W')
            ->setCategorie($categorie);
        $manager->persist($materiel);

        for ($i = 1; $i <= 3; ++$i) {
            $exemplaire = (new Exemplaire())
                ->setNumeroInventaire(sprintf('VP-%03d', $i))
                ->setEtat(EtatExemplaire::DISPONIBLE)
                ->setMateriel($materiel);
            $manager->persist($exemplaire);
            $this->addReference(self::EXEMPLAIRE_PREFIXE . $i, $exemplaire);
        }

        $manager->flush();
    }
}
