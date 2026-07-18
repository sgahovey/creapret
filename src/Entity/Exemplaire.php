<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EtatExemplaire;
use App\Repository\ExemplaireRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExemplaireRepository::class)]
#[ORM\Table(name: 'exemplaire')]
#[ORM\UniqueConstraint(name: 'uniq_exemplaire_numero', columns: ['numero_inventaire'])]
#[ORM\Index(name: 'idx_exemplaire_materiel_etat', columns: ['id_materiel', 'etat'])]
#[UniqueEntity(fields: ['numeroInventaire'], message: 'Ce numéro d\'inventaire est déjà utilisé.')]
class Exemplaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'numero_inventaire', length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $numeroInventaire;

    #[ORM\Column(length: 20, enumType: EtatExemplaire::class)]
    private EtatExemplaire $etat = EtatExemplaire::DISPONIBLE;

    #[ORM\ManyToOne(inversedBy: 'exemplaires')]
    #[ORM\JoinColumn(name: 'id_materiel', nullable: false, onDelete: 'RESTRICT')]
    private Materiel $materiel;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroInventaire(): string
    {
        return $this->numeroInventaire;
    }

    public function setNumeroInventaire(string $numeroInventaire): static
    {
        $this->numeroInventaire = $numeroInventaire;

        return $this;
    }

    public function getEtat(): EtatExemplaire
    {
        return $this->etat;
    }

    public function setEtat(EtatExemplaire $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getMateriel(): Materiel
    {
        return $this->materiel;
    }

    public function setMateriel(Materiel $materiel): static
    {
        $this->materiel = $materiel;

        return $this;
    }
}
