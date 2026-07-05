<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Utilisateur;
use App\Validator\MotDePasseFort;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire d'inscription publique (US-1.2). Cree exclusivement des emprunteurs :
 * aucun champ role expose (protection contre l'elevation de privilege).
 *
 * @extends AbstractType<Utilisateur>
 */
final class InscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr'  => ['autocomplete' => 'email'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prenom',
                'attr'  => ['autocomplete' => 'given-name'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr'  => ['autocomplete' => 'family-name'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label'       => 'Mot de passe',
                'mapped'      => false,
                'attr'        => ['autocomplete' => 'new-password'],
                'help'        => 'Au moins 12 caracteres, avec majuscule, minuscule, chiffre et caractere special.',
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                    new MotDePasseFort(),
                ],
            ])
            ->add('accepteCgu', CheckboxType::class, [
                'label'       => 'J\'accepte les conditions generales d\'utilisation.',
                'mapped'      => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter les conditions generales d\'utilisation.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
