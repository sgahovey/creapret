<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Donnees d'une demande de pret saisies par l'emprunteur (CU-04).
 *
 * Porte les contraintes de FORMAT et de COHERENCE des dates (validation syntaxique). La regle
 * metier RG-4 (au moins un exemplaire libre sur la periode) depend de l'etat de la base et est
 * verifiee dans le controleur, pas ici.
 */
final class DemandePret
{
    #[Assert\NotNull(message: 'Indiquez une date de début.')]
    #[Assert\GreaterThanOrEqual('today', message: 'La période ne peut pas commencer dans le passé.')]
    public ?\DateTimeImmutable $debut = null;

    #[Assert\NotNull(message: 'Indiquez une date de fin.')]
    #[Assert\GreaterThan(propertyPath: 'debut', message: 'La date de fin doit être postérieure à la date de début.')]
    public ?\DateTimeImmutable $fin = null;
}
