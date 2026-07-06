<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\DemandePret;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de demande de pret. Adosse au DTO DemandePret (les contraintes vivent sur le DTO).
 * Les DateType sont en datetime_immutable pour rester coherents avec les dates de l'entite Pret.
 *
 * @extends AbstractType<DemandePret>
 */
final class DemandePretType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('debut', DateType::class, [
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'label'  => 'Du',
            ])
            ->add('fin', DateType::class, [
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'label'  => 'Au',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandePret::class,
        ]);
    }
}
