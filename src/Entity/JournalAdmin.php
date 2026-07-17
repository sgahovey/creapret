<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeActionJournal;
use App\Repository\JournalAdminRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal des actions d'administration (RGPD, US-5.3).
 *
 * Entree append-only : une decision d'administration (validation, refus, retour d'un pret) y est
 * enregistree avec son acteur et sa cible FIGES (identifiant + libelle au moment de l'action). Aucune
 * cle etrangere n'est posee : la trace survit a la desactivation ou au renommage d'un compte et reste
 * lisible sans jointure. Consultable par le super-administrateur, purgee au-dela de la duree de
 * conservation (limitation de conservation RGPD).
 */
#[ORM\Entity(repositoryClass: JournalAdminRepository::class)]
#[ORM\Table(name: 'journal_admin')]
#[ORM\Index(name: 'idx_journal_admin_date', columns: ['date_action'])]
class JournalAdmin
{
    /** Duree de conservation par defaut (en jours) avant purge (limitation de conservation RGPD). */
    public const int DUREE_CONSERVATION_JOURS = 365;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'date_action', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateAction;

    #[ORM\Column(name: 'type_action', length: 40, enumType: TypeActionJournal::class)]
    private TypeActionJournal $typeAction;

    #[ORM\Column(name: 'acteur_id')]
    private int $acteurId;

    #[ORM\Column(name: 'acteur_libelle', length: 201)]
    private string $acteurLibelle;

    #[ORM\Column(name: 'cible_id', nullable: true)]
    private ?int $cibleId = null;

    #[ORM\Column(name: 'cible_libelle', length: 201, nullable: true)]
    private ?string $cibleLibelle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    public function __construct(
        TypeActionJournal $typeAction,
        int $acteurId,
        string $acteurLibelle,
        ?int $cibleId = null,
        ?string $cibleLibelle = null,
        ?string $details = null,
    ) {
        $this->dateAction = new \DateTimeImmutable();
        $this->typeAction = $typeAction;
        $this->acteurId = $acteurId;
        $this->acteurLibelle = $acteurLibelle;
        $this->cibleId = $cibleId;
        $this->cibleLibelle = $cibleLibelle;
        $this->details = $details;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateAction(): \DateTimeImmutable
    {
        return $this->dateAction;
    }

    public function getTypeAction(): TypeActionJournal
    {
        return $this->typeAction;
    }

    public function getActeurId(): int
    {
        return $this->acteurId;
    }

    public function getActeurLibelle(): string
    {
        return $this->acteurLibelle;
    }

    public function getCibleId(): ?int
    {
        return $this->cibleId;
    }

    public function getCibleLibelle(): ?string
    {
        return $this->cibleLibelle;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }
}
