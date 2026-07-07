<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Exemplaire;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Enum\StatutPret;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Donnees de demonstration : un super-administrateur, un gestionnaire et plusieurs emprunteurs
 * (adresses de demo sous-adressees vers une boite unique, +role pour l'administration, +prenom pour
 * les emprunteurs), plus deux prets VALIDE dont les periodes chevauchent la date du jour, afin que
 * le calendrier d'occupation montre plusieurs evenements. Depend de ReferenceFixtures.
 */
final class DemoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private const MOT_DE_PASSE = 'Motdepasse123!';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public static function getGroups(): array
    {
        return ['demo'];
    }

    public function getDependencies(): array
    {
        return [ReferenceFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // Comptes d'administration (email = role).
        $superAdmin = $this->creerUtilisateur('creapretdemo+superadmin@gmail.com', 'Admin', 'Sacha', Role::SUPER_ADMIN);
        $gestionnaire = $this->creerUtilisateur('creapretdemo+gestionnaire@gmail.com', 'Gestion', 'Gerard', Role::GESTIONNAIRE);
        $manager->persist($superAdmin);
        $manager->persist($gestionnaire);

        // Emprunteurs (email = prenom).
        $marie = $this->creerUtilisateur('creapretdemo+marie@gmail.com', 'Dupont', 'Marie', Role::EMPRUNTEUR);
        $jean = $this->creerUtilisateur('creapretdemo+jean@gmail.com', 'Martin', 'Jean', Role::EMPRUNTEUR);
        $sophie = $this->creerUtilisateur('creapretdemo+sophie@gmail.com', 'Bernard', 'Sophie', Role::EMPRUNTEUR);
        $manager->persist($marie);
        $manager->persist($jean);
        $manager->persist($sophie);

        /** @var Exemplaire $vp1 */
        $vp1 = $this->getReference(ReferenceFixtures::EXEMPLAIRE_PREFIXE . '1', Exemplaire::class);
        /** @var Exemplaire $vp2 */
        $vp2 = $this->getReference(ReferenceFixtures::EXEMPLAIRE_PREFIXE . '2', Exemplaire::class);

        // Deux prets VALIDE chevauchant aujourd'hui (2026-07-07) : plusieurs evenements sur le calendrier.
        $manager->persist($this->creerPretValide($marie, $gestionnaire, $vp1, '2026-07-05', '2026-07-12'));
        $manager->persist($this->creerPretValide($jean, $gestionnaire, $vp2, '2026-07-08', '2026-07-15'));

        $manager->flush();
    }

    private function creerUtilisateur(string $email, string $nom, string $prenom, Role $role): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setEmail($email)
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setRole($role)
            ->setEstActif(true);
        $utilisateur->setMotDePasseHash($this->hasher->hashPassword($utilisateur, self::MOT_DE_PASSE));

        return $utilisateur;
    }

    private function creerPretValide(
        Utilisateur $emprunteur,
        Utilisateur $validateur,
        Exemplaire $exemplaire,
        string $debut,
        string $fin,
    ): Pret {
        return (new Pret())
            ->setEmprunteur($emprunteur)
            ->setExemplaire($exemplaire)
            ->setDateDebut(new \DateTimeImmutable($debut))
            ->setDateFin(new \DateTimeImmutable($fin))
            ->setStatut(StatutPret::VALIDE)
            ->setValidateur($validateur)
            ->setDateValidation(new \DateTimeImmutable($debut . ' -1 day'));
    }
}
