<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Role;
use App\Repository\UtilisateurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[ORM\UniqueConstraint(name: 'uniq_utilisateur_email', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse e-mail est deja utilisee.')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email;

    #[ORM\Column(name: 'mot_de_passe_hash', length: 255)]
    private string $motDePasseHash;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $nom;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $prenom;

    #[ORM\Column(length: 30, enumType: Role::class)]
    #[Assert\NotNull]
    private Role $role = Role::EMPRUNTEUR;

    #[ORM\Column(name: 'est_actif')]
    private bool $estActif = true;

    #[ORM\Column(name: 'email_rappel')]
    private bool $emailRappel = true;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateCreation;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Identifiant de securite (Symfony).
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        // Invariant : l'identifiant Security ne peut pas etre vide (garanti par
        // NotBlank/Email), verifie ici explicitement (defense en profondeur).
        if ('' === $this->email) {
            throw new \LogicException('Adresse email manquante : identifiant utilisateur invalide.');
        }

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique(['ROLE_' . strtoupper($this->role->value), 'ROLE_USER']));
    }

    public function getPassword(): string
    {
        return $this->motDePasseHash;
    }

    public function getMotDePasseHash(): string
    {
        return $this->motDePasseHash;
    }

    public function setMotDePasseHash(string $motDePasseHash): static
    {
        $this->motDePasseHash = $motDePasseHash;

        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getNomComplet(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isEstActif(): bool
    {
        return $this->estActif;
    }

    public function setEstActif(bool $estActif): static
    {
        $this->estActif = $estActif;

        return $this;
    }

    public function isEmailRappel(): bool
    {
        return $this->emailRappel;
    }

    public function setEmailRappel(bool $emailRappel): static
    {
        $this->emailRappel = $emailRappel;

        return $this;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function eraseCredentials(): void
    {
        // Aucun secret transitoire stocke en clair.
    }

    /**
     * Deauthentifie immediatement un compte desactive ou dont le role a change
     * sur un firewall stateful (le mot de passe est volontairement exclu).
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        return $this->getUserIdentifier() === $user->getUserIdentifier()
            && $this->estActif === $user->isEstActif()
            && $this->getRoles() === $user->getRoles();
    }
}
