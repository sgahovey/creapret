<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Validator\MotDePasseFort;
use App\Validator\MotDePasseFortValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<MotDePasseFortValidator>
 */
final class MotDePasseFortValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): MotDePasseFortValidator
    {
        return new MotDePasseFortValidator();
    }

    public function test_un_mot_de_passe_conforme_ne_leve_aucune_violation(): void
    {
        $this->validator->validate('Motdepasse1!', new MotDePasseFort());
        $this->assertNoViolation();
    }

    public function test_trop_court_est_rejete(): void
    {
        $this->validator->validate('Ab1!', new MotDePasseFort());
        $this->buildViolation('Le mot de passe doit contenir au moins {{ min }} caractères.')
            ->setParameter('{{ min }}', '12')
            ->assertRaised();
    }

    public function test_sans_majuscule_est_rejete(): void
    {
        $this->validator->validate('motdepasse1!', new MotDePasseFort());
        $this->buildViolation('Le mot de passe doit contenir au moins une lettre majuscule.')
            ->assertRaised();
    }

    public function test_sans_chiffre_est_rejete(): void
    {
        $this->validator->validate('Motdepassefort!', new MotDePasseFort());
        $this->buildViolation('Le mot de passe doit contenir au moins un chiffre.')
            ->assertRaised();
    }

    public function test_sans_caractere_special_est_rejete(): void
    {
        $this->validator->validate('Motdepasse12', new MotDePasseFort());
        $this->buildViolation('Le mot de passe doit contenir au moins un caractère spécial.')
            ->assertRaised();
    }

    public function test_valeur_vide_est_ignoree(): void
    {
        $this->validator->validate('', new MotDePasseFort());
        $this->assertNoViolation();
    }
}
