<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Materiel;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Materiel>
 */
final class MaterielType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr'  => ['maxlength' => 150],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 3],
            ])
            ->add('marque', TextType::class, [
                'label'    => 'Marque',
                'required' => false,
                'attr'     => ['maxlength' => 100],
            ])
            ->add('modele', TextType::class, [
                'label'    => 'Modele',
                'required' => false,
                'attr'     => ['maxlength' => 100],
            ])
            ->add('reference', TextType::class, [
                'label'    => 'Reference',
                'required' => false,
                'attr'     => ['maxlength' => 100],
            ])
            ->add('categorie', EntityType::class, [
                'class'         => Categorie::class,
                'choice_label'  => 'nom',
                'placeholder'   => 'Choisir une categorie',
                'label'         => 'Categorie',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('c')->orderBy('c.nom', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Materiel::class,
        ]);
    }
}
