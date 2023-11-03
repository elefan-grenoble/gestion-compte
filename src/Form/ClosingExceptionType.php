<?php

namespace App\Form;

use App\Entity\ClosingException;
use App\Entity\Period;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClosingExceptionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date', DateType::class, ['html5' => false, 'widget' => 'single_text', 'label' => 'Date de la fermeture exceptionnelle', 'attr' => ['class' => 'datepicker']])
            ->add('reason', TextareaType::class, ['label' => 'Raison', 'required' => false, 'attr' => ['class' => 'materialize-textarea']]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ClosingException::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'App_closing_exception';
    }
}
