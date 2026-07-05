<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class MotDePasseFortValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MotDePasseFort) {
            throw new UnexpectedTypeException($constraint, MotDePasseFort::class);
        }

        if (null === $value || '' === $value) {
            return; // la contrainte NotBlank gere le cas vide
        }

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (\strlen($value) < $constraint->longueurMin) {
            $this->context->buildViolation($constraint->messageTropCourt)
                ->setParameter('{{ min }}', (string) $constraint->longueurMin)
                ->addViolation();
        }
        if (!preg_match('/\p{Ll}/u', $value)) {
            $this->context->buildViolation($constraint->messageMinuscule)->addViolation();
        }
        if (!preg_match('/\p{Lu}/u', $value)) {
            $this->context->buildViolation($constraint->messageMajuscule)->addViolation();
        }
        if (!preg_match('/\d/', $value)) {
            $this->context->buildViolation($constraint->messageChiffre)->addViolation();
        }
        if (!preg_match('/[^\p{L}\d]/u', $value)) {
            $this->context->buildViolation($constraint->messageSpecial)->addViolation();
        }
    }
}
