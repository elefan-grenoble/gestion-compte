<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;

class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('display_gauge', CheckboxType::class, ['label' => 'Afficher la gauge', 'required' => false, 'attr' => ['class' => 'filled-in']])
            ->add('main_color', ColorType::class, ['label' => 'Couleur principale', 'required' => false, 'attr' => ['class' => 'filled-in']])
        ;
    }
}
