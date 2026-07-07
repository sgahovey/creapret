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
 * Donnees de demonstration : comptes par role, prets VALIDE en cours (dont un finissant demain pour la
 * demo du rappel J-1) et un historique de prets RETOURNE. L'historique alimente le classement des
 * materiels les plus empruntes du tableau de bord ; comme RG-1 interdit deux prets VALIDE chevauchants
 * sur un meme exemplaire, la volumetrie passee est representee par des prets RETOURNE (periodes
 * anterieures, date de retour renseignee). Depend de ReferenceFixtures.
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
        $superAdmin = $this->creerUtilisateur('creapretdemo+superadmin@gmail.com', 'Admin', 'Sacha', Role::SUPER_ADMIN);
        $gestionnaire = $this->creerUtilisateur('creapretdemo+gestionnaire@gmail.com', 'Gestion', 'Gerard', Role::GESTIONNAIRE);
        $manager->persist($superAdmin);
        $manager->persist($gestionnaire);

        $marie = $this->creerUtilisateur('creapretdemo+marie@gmail.com', 'Dupont', 'Marie', Role::EMPRUNTEUR);
        $jean = $this->creerUtilisateur('creapretdemo+jean@gmail.com', 'Martin', 'Jean', Role::EMPRUNTEUR);
        $sophie = $this->creerUtilisateur('creapretdemo+sophie@gmail.com', 'Bernard', 'Sophie', Role::EMPRUNTEUR);
        $manager->persist($marie);
        $manager->persist($jean);
        $manager->persist($sophie);
        $emprunteurs = [$marie, $jean, $sophie];

        // --- Prets VALIDE en cours (KPI "prets en cours" + calendrier d'occupation) ---
        $vp1 = $this->exemplaire('videoprojecteur-1');
        $vp2 = $this->exemplaire('videoprojecteur-2');
        $vp3 = $this->exemplaire('videoprojecteur-3');
        $manager->persist($this->creerPretValide($marie, $gestionnaire, $vp1, '2026-07-05', '2026-07-12'));
        $manager->persist($this->creerPretValide($jean, $gestionnaire, $vp2, '2026-07-08', '2026-07-15'));
        // Pret finissant DEMAIN (2026-07-08) : cible du rappel J-1 pour la demo.
        $manager->persist($this->creerPretValide($sophie, $gestionnaire, $vp3, '2026-07-03', '2026-07-08'));

        // --- Historique de prets RETOURNE : volumes decroissants pour un Top 5 parlant ---
        $volumes = [
            'videoprojecteur' => 5,
            'pc'              => 6,
            'souris'          => 4,
            'casque'          => 3,
            'clavier'         => 2,
            'webcam'          => 1,
        ];

        $exemplairesParMateriel = [
            'videoprojecteur' => 3, 'pc' => 4, 'souris' => 5, 'casque' => 3, 'clavier' => 3, 'webcam' => 2,
        ];

        $joursAvant = 90;
        foreach ($volumes as $cleMateriel => $nombre) {
            $nbExemplaires = $exemplairesParMateriel[$cleMateriel];
            for ($n = 0; $n < $nombre; ++$n) {
                $exemplaire = $this->exemplaire($cleMateriel . '-' . (($n % $nbExemplaires) + 1));
                $emprunteur = $emprunteurs[$n % count($emprunteurs)];
                $debut = sprintf('-%d days', $joursAvant);
                $fin = sprintf('-%d days', $joursAvant - 5);
                $manager->persist($this->creerPretRetourne($emprunteur, $gestionnaire, $exemplaire, $debut, $fin));
                $joursAvant -= 6;
                if ($joursAvant < 10) {
                    $joursAvant = 90;
                }
            }
        }

        $manager->flush();
    }

    private function exemplaire(string $cle): Exemplaire
    {
        /** @var Exemplaire $exemplaire */
        $exemplaire = $this->getReference(ReferenceFixtures::EXEMPLAIRE_PREFIXE . $cle, Exemplaire::class);

        return $exemplaire;
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

    private function creerPretRetourne(
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
            ->setStatut(StatutPret::RETOURNE)
            ->setValidateur($validateur)
            ->setDateValidation(new \DateTimeImmutable($debut . ' -1 day'))
            ->setDateRetour(new \DateTimeImmutable($fin));
    }
}
