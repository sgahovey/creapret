<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Exemplaire;
use App\Entity\JournalAdmin;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Enum\TypeActionJournal;
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
 * anterieures, date de retour renseignee). Alimente aussi le journal d'administration (US-5.3 / US-6.2),
 * trace append-only consultee par le super-administrateur. Depend de ReferenceFixtures.
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

        // --- Journal d'administration (US-5.3 / US-6.2) : trace consultee par le super-administrateur.
        // Les prets de demo sont crees directement en base (sans passer par le controleur qui alimente
        // normalement le journal) ; on reconstitue donc ici des entrees representatives -- decisions de
        // pret d'un gestionnaire et actions de compte du super-administrateur -- pour que l'ecran de
        // consultation ne soit pas vide. L'acteur et la cible sont figes (id + libelle) : leurs IDs ne
        // sont connus qu'apres le premier flush, d'ou ce second bloc. Les dates couvrent les huit cas de
        // l'enum et sont coherentes avec les roles/etats reellement seedes (Gerard finit gestionnaire,
        // Jean reste actif apres reactivation). ---
        $this->entreeJournal($manager, TypeActionJournal::COMPTE_CREATION, $superAdmin, $gestionnaire, 'Compte cree avec le role emprunteur', '2026-04-10 08:05:00');
        $this->entreeJournal($manager, TypeActionJournal::COMPTE_CHANGEMENT_ROLE, $superAdmin, $gestionnaire, 'Role modifie : emprunteur vers gestionnaire', '2026-04-11 09:15:00');
        $this->entreeJournal($manager, TypeActionJournal::COMPTE_DESACTIVATION, $superAdmin, $jean, 'Compte suspendu temporairement', '2026-05-06 10:40:00');
        $this->entreeJournal($manager, TypeActionJournal::COMPTE_ACTIVATION, $superAdmin, $jean, 'Compte reactive', '2026-05-20 11:00:00');
        $this->entreeJournal($manager, TypeActionJournal::COMPTE_MODIFICATION, $superAdmin, $marie, 'Correction du nom de famille', '2026-06-02 14:25:00');

        $this->entreeJournal($manager, TypeActionJournal::PRET_RETOUR, $gestionnaire, $sophie, 'Retour avec dommage : coque rayee', '2026-05-15 09:30:00');
        $this->entreeJournal($manager, TypeActionJournal::PRET_REFUS, $gestionnaire, $marie, 'Aucun exemplaire disponible sur la periode demandee', '2026-06-18 16:10:00');
        $this->entreeJournal($manager, TypeActionJournal::PRET_RETOUR, $gestionnaire, $jean, 'Retour conforme', '2026-06-25 10:05:00');
        $this->entreeJournal($manager, TypeActionJournal::PRET_VALIDATION, $gestionnaire, $sophie, null, '2026-07-02 08:30:00');
        $this->entreeJournal($manager, TypeActionJournal::PRET_VALIDATION, $gestionnaire, $marie, null, '2026-07-04 09:45:00');
        $this->entreeJournal($manager, TypeActionJournal::PRET_VALIDATION, $gestionnaire, $jean, null, '2026-07-07 15:20:00');

        $manager->flush();
    }

    /**
     * Cree une entree de journal d'administration a une date choisie. Le constructeur de JournalAdmin
     * fige `dateAction` a l'instant courant (entree append-only, sans setter) ; pour la demo on recale
     * cette date par reflexion -- meme approche que PurgeAuditCommandTest.
     */
    private function entreeJournal(
        ObjectManager $manager,
        TypeActionJournal $type,
        Utilisateur $acteur,
        ?Utilisateur $cible,
        ?string $details,
        string $date,
    ): void {
        $entree = new JournalAdmin(
            typeAction: $type,
            acteurId: (int) $acteur->getId(),
            acteurLibelle: $acteur->getNomComplet(),
            cibleId: $cible?->getId(),
            cibleLibelle: $cible?->getNomComplet(),
            details: $details,
        );
        (new \ReflectionProperty(JournalAdmin::class, 'dateAction'))
            ->setValue($entree, new \DateTimeImmutable($date));
        $manager->persist($entree);
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
