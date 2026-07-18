<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Motif de refus d'une demande de pret, saisi par le gestionnaire (CU-07). Obligatoire :
 * l'emprunteur doit savoir pourquoi sa demande est refusee.
 */
final class RefusPret
{
    #[Assert\NotBlank(message: 'Le motif de refus est obligatoire.')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Motif trop court.', maxMessage: 'Motif trop long (255 caractères max).')]
    public ?string $motif = null;
}
