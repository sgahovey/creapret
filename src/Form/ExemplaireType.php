<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Enum\EtatExemplaire;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Exemplaire>
 */
final class ExemplaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroInventaire', TextType::class, [
                'label' => 'Numero d\'inventaire',
                'attr'  => ['maxlength' => 50],
            ])
            ->add('materiel', EntityType::class, [
                'class'         => Materiel::class,
                'choice_label'  => 'nom',
                'placeholder'   => 'Choisir un materiel',
                'label'         => 'Materiel',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('m')->orderBy('m.nom', 'ASC'),
            ])
            ->add('etat', EnumType::class, [
                'class' => EtatExemplaire::class,
                'label' => 'Etat',
                // PRETE exclu : cet etat resulte d'un pret valide (iteration 3),
                // il n'est pas choisi manuellement par le gestionnaire.
                'choices' => array_filter(
                    EtatExemplaire::cases(),
                    static fn (EtatExemplaire $e) => EtatExemplaire::PRETE !== $e,
                ),
                'choice_label' => static fn (EtatExemplaire $e) => $e->libelle(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Exemplaire::class,
        ]);
    }
}
