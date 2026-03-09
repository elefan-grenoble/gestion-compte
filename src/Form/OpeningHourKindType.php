<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OpeningHourKindType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'required' => true,
            ])
            ->add('start_date', DateType::class, [
                'label' => 'Date de début (optionnel)',
                'required' => false,
                'html5' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'datepicker'],
            ])
            ->add('end_date', DateType::class, [
                'label' => 'Date de fin (optionnel)',
                'required' => false,
                'html5' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'datepicker'],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => ['class' => 'filled-in'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'App\Entity\OpeningHourKind',
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_OpeningHourKind';
    }
}
