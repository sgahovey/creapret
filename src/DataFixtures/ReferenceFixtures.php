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
 * Donnees de reference stables (catalogue) : deux categories et plusieurs materiels representatifs
 * d'un parc pedagogique (materiel de projection et poste de travail etudiant), chacun avec plusieurs
 * exemplaires disponibles. Chaque exemplaire est expose via une reference indexee
 * (EXEMPLAIRE_PREFIXE . cle) afin que DemoFixtures cree des prets dessus.
 */
final class ReferenceFixtures extends Fixture implements FixtureGroupInterface
{
    public const EXEMPLAIRE_PREFIXE = 'exemplaire-';

    /**
     * Catalogue de reference : pour chaque materiel, sa categorie, son prefixe d'inventaire,
     * le nombre d'exemplaires et une cle de reference stable.
     *
     * @var list<array{cle: string, nom: string, categorie: string, prefixe: string, exemplaires: int}>
     */
    private const CATALOGUE = [
        ['cle' => 'videoprojecteur', 'nom' => 'Videoprojecteur Epson EB-982W', 'categorie' => 'Video', 'prefixe' => 'VP', 'exemplaires' => 3],
        ['cle' => 'micro', 'nom' => 'Micro-cravate sans fil', 'categorie' => 'Video', 'prefixe' => 'MC', 'exemplaires' => 2],
        ['cle' => 'casque', 'nom' => 'Casque audio', 'categorie' => 'Video', 'prefixe' => 'CA', 'exemplaires' => 3],
        ['cle' => 'pc', 'nom' => 'PC portable', 'categorie' => 'Informatique', 'prefixe' => 'PC', 'exemplaires' => 4],
        ['cle' => 'chargeur', 'nom' => 'Chargeur PC portable', 'categorie' => 'Informatique', 'prefixe' => 'CH', 'exemplaires' => 4],
        ['cle' => 'souris', 'nom' => 'Souris ergonomique Logitech', 'categorie' => 'Informatique', 'prefixe' => 'SO', 'exemplaires' => 5],
        ['cle' => 'clavier', 'nom' => 'Clavier mecanique', 'categorie' => 'Informatique', 'prefixe' => 'CL', 'exemplaires' => 3],
        ['cle' => 'webcam', 'nom' => 'Webcam HD', 'categorie' => 'Informatique', 'prefixe' => 'WC', 'exemplaires' => 2],
        ['cle' => 'ssd', 'nom' => 'Disque dur externe SSD', 'categorie' => 'Informatique', 'prefixe' => 'SSD', 'exemplaires' => 2],
    ];

    private const DESCRIPTIONS_CATEGORIES = [
        'Video'        => 'Materiel de projection, de captation et de diffusion audio-video.',
        'Informatique' => 'Materiel informatique et peripheriques pour le poste de travail etudiant.',
    ];

    public static function getGroups(): array
    {
        return ['reference'];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var array<string, Categorie> $categories */
        $categories = [];

        foreach (self::CATALOGUE as $ligne) {
            $nomCategorie = $ligne['categorie'];
            if (!isset($categories[$nomCategorie])) {
                $categorie = (new Categorie())
                    ->setNom($nomCategorie)
                    ->setDescription(self::DESCRIPTIONS_CATEGORIES[$nomCategorie]);
                $manager->persist($categorie);
                $categories[$nomCategorie] = $categorie;
            }

            $materiel = (new Materiel())
                ->setNom($ligne['nom'])
                ->setCategorie($categories[$nomCategorie]);
            $manager->persist($materiel);

            for ($i = 1; $i <= $ligne['exemplaires']; ++$i) {
                $exemplaire = (new Exemplaire())
                    ->setNumeroInventaire(sprintf('%s-%03d', $ligne['prefixe'], $i))
                    ->setEtat(EtatExemplaire::DISPONIBLE)
                    ->setMateriel($materiel);
                $manager->persist($exemplaire);
                $this->addReference(self::EXEMPLAIRE_PREFIXE . $ligne['cle'] . '-' . $i, $exemplaire);
            }
        }

        $manager->flush();
    }
}
