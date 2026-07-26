<?php

namespace App\Form;

use App\Entity\ClosingException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClosingExceptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date', DateType::class, ['html5' => false, 'widget' => 'single_text', 'label' => 'Date de la fermeture exceptionnelle', 'attr' => ['class' => 'datepicker']])
            ->add('reason', TextareaType::class, ['label' => 'Raison', 'required' => false, 'attr' => ['class' => 'materialize-textarea']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ClosingException::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_closing_exception';
    }
}
