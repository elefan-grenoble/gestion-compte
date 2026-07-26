<?php

namespace App\Form;

use App\Entity\TimeLog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeLogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('description', TextareaType::class, ['required' => true, 'label' => 'Motif', 'attr' => ['class' => 'materialize-textarea']]);
        $builder->add('time', NumberType::class, ['required' => true, 'label' => 'Valeur négative ou positive en minutes']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => TimeLog::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_note';
    }
}
