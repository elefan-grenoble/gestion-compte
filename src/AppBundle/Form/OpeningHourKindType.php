<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OpeningHourKindType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, array(
                'label' => 'Nom',
                'required' => true
            ))
            ->add('start_date', DateType::class, array(
                'label' => 'Date de début (optionnel)',
                'required' => false,
                'html5' => false,
                'widget' => 'single_text',
                'attr' => array('class' => 'datepicker')
            ))
            ->add('end_date', DateType::class, array(
                'label' => 'Date de fin (optionnel)',
                'required' => false,
                'html5' => false,
                'widget' => 'single_text',
                'attr' => array('class' => 'datepicker')
            ))
            ->add('enabled', CheckboxType::class, array(
                'label' => 'Actif',
                'required' => false,
                'attr' => array('class' => 'filled-in')
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\OpeningHourKind'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_OpeningHourKind';
    }
}
