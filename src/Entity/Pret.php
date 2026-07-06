<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutPret;
use App\Repository\PretRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un pret d'un exemplaire a un emprunteur sur une periode.
 *
 * Le verrou pessimiste (US-3.4) portera sur l'exemplaire ; l'index critique idx_pret_dispo
 * (bout suivant) soutiendra le test de chevauchement des prets VALIDE (RG-1/RG-4).
 */
#[ORM\Entity(repositoryClass: PretRepository::class)]
#[ORM\Table(name: 'pret')]
#[ORM\Index(name: 'idx_pret_dispo', columns: ['id_exemplaire', 'statut', 'date_debut', 'date_fin'])]
#[ORM\Index(name: 'idx_pret_emprunteur', columns: ['id_emprunteur'])]
class Pret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateFin;

    #[ORM\Column(length: 20, enumType: StatutPret::class)]
    private StatutPret $statut;

    #[ORM\Column(name: 'motif_refus', length: 255, nullable: true)]
    private ?string $motifRefus = null;

    #[ORM\Column(name: 'date_demande', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateDemande;

    #[ORM\Column(name: 'date_validation', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateValidation = null;

    #[ORM\Column(name: 'date_retour', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateRetour = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_exemplaire', nullable: false, onDelete: 'RESTRICT')]
    private Exemplaire $exemplaire;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_emprunteur', nullable: false, onDelete: 'RESTRICT')]
    private Utilisateur $emprunteur;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_validateur', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $validateur = null;

    public function __construct()
    {
        // A la creation, le pret est une demande en attente, horodatee.
        $this->statut = StatutPret::DEMANDE;
        $this->dateDemande = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDebut(): \DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): \DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getStatut(): StatutPret
    {
        return $this->statut;
    }

    public function setStatut(StatutPret $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getMotifRefus(): ?string
    {
        return $this->motifRefus;
    }

    public function setMotifRefus(?string $motifRefus): static
    {
        $this->motifRefus = $motifRefus;

        return $this;
    }

    public function getDateDemande(): \DateTimeImmutable
    {
        return $this->dateDemande;
    }

    public function setDateDemande(\DateTimeImmutable $dateDemande): static
    {
        $this->dateDemande = $dateDemande;

        return $this;
    }

    public function getDateValidation(): ?\DateTimeImmutable
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeImmutable $dateValidation): static
    {
        $this->dateValidation = $dateValidation;

        return $this;
    }

    public function getDateRetour(): ?\DateTimeImmutable
    {
        return $this->dateRetour;
    }

    public function setDateRetour(?\DateTimeImmutable $dateRetour): static
    {
        $this->dateRetour = $dateRetour;

        return $this;
    }

    public function getExemplaire(): Exemplaire
    {
        return $this->exemplaire;
    }

    public function setExemplaire(Exemplaire $exemplaire): static
    {
        $this->exemplaire = $exemplaire;

        return $this;
    }

    public function getEmprunteur(): Utilisateur
    {
        return $this->emprunteur;
    }

    public function setEmprunteur(Utilisateur $emprunteur): static
    {
        $this->emprunteur = $emprunteur;

        return $this;
    }

    public function getValidateur(): ?Utilisateur
    {
        return $this->validateur;
    }

    public function setValidateur(?Utilisateur $validateur): static
    {
        $this->validateur = $validateur;

        return $this;
    }
}
