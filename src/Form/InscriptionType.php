<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Utilisateur;
use App\Validator\MotDePasseFort;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

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
            ->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options'   => [
                    'label'       => 'Mot de passe',
                    'attr'        => ['autocomplete' => 'new-password', 'data-afficher-mot-de-passe-target' => 'champ'],
                    'help'        => 'Au moins 12 caractères, avec majuscule, minuscule, chiffre et caractère spécial.',
                    'constraints' => [
                        new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                        new MotDePasseFort(),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr'  => ['autocomplete' => 'new-password', 'data-afficher-mot-de-passe-target' => 'champ'],
                ],
            ])
            ->add('accepteCgu', CheckboxType::class, [
                // Le consentement porte sur la politique de confidentialite (RGPD) : le libelle
                // renvoie a la page pour que la personne lise ce a quoi elle consent avant d'accepter.
                // (Le nom du champ reste 'accepteCgu' -- ecart de nommage interne assume, cf. DT-11.)
                'label'       => sprintf(
                    'J\'ai lu et j\'accepte la <a href="%s" target="_blank" rel="noopener">politique de confidentialité</a>.',
                    $this->urlGenerator->generate('app_confidentialite'),
                ),
                'label_html'  => true,
                'mapped'      => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter la politique de confidentialité pour créer un compte.'),
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
