<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\RefusPret;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RefusPret>
 */
final class RefusPretType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('motif', TextareaType::class, [
            'label' => 'Motif du refus',
            'attr'  => ['rows' => 3],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RefusPret::class]);
    }
}
