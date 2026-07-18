<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Validator\MotDePasseFort;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire d'administration d'un compte (US-6.2), cote super-administrateur. Un seul type pour la
 * creation et l'edition : l'option `avec_mot_de_passe` (vrai a la creation, faux en edition)
 * conditionne la presence du champ mot de passe. Distinct d'InscriptionType (auto-inscription
 * publique) : pas de CGU, et un champ role gere par l'administrateur.
 *
 * @extends AbstractType<Utilisateur>
 */
final class UtilisateurAdminType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr'  => ['autocomplete' => 'email'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr'  => ['autocomplete' => 'given-name'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr'  => ['autocomplete' => 'family-name'],
            ])
            ->add('role', EnumType::class, [
                'class'        => Role::class,
                'label'        => 'Rôle',
                'choice_label' => fn (Role $role): string => $role->libelle(),
            ])
            ->add('estActif', CheckboxType::class, [
                'label'    => 'Compte actif',
                'required' => false,
            ])
            ->add('emailRappel', CheckboxType::class, [
                'label'    => 'Recevoir les e-mails de rappel',
                'required' => false,
            ]);

        if (true === $options['avec_mot_de_passe']) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options'   => [
                    'label'       => 'Mot de passe',
                    'attr'        => ['autocomplete' => 'new-password'],
                    'help'        => 'Au moins 12 caractères, avec majuscule, minuscule, chiffre et caractère spécial.',
                    'constraints' => [
                        new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                        new MotDePasseFort(),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr'  => ['autocomplete' => 'new-password'],
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'        => Utilisateur::class,
            'csrf_token_id'     => 'utilisateur_admin',
            'avec_mot_de_passe' => false,
        ]);
        $resolver->setAllowedTypes('avec_mot_de_passe', 'bool');
    }
}
