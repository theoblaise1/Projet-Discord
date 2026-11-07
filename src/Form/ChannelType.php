<?php

namespace App\Form;

use App\Entity\Channel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Création et modification d'un salon (les règles de validation sont dans l'entité Channel).
 */
class ChannelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du salon',
                'help' => 'De 2 à 50 caractères. Exemple : aide-symfony',
                'attr' => [
                    'placeholder' => 'nouveau-salon',
                    'maxlength' => 50,
                    'autofocus' => true,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Sujet du salon',
                'required' => false,
                'help' => 'Facultatif. Affiché en haut du salon pour indiquer de quoi on y parle.',
                'attr' => [
                    'placeholder' => 'De quoi parle-t-on ici ?',
                    'maxlength' => 255,
                    'rows' => 3,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Channel::class,
        ]);
    }
}
