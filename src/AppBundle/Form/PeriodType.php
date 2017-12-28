<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PeriodType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('day_of_week',ChoiceType::class,array('label'=>'Jour de la semaine','choices' => array(
                "Lundi" => 0,
                "Mardi" => 1,
                "Mercredi" => 2,
                "Jeudi" => 3,
                "Vendredi" => 4,
                "Samedi" => 5,
                "Dimanche" => 6,
            )))
            ->add('start',TextType::class,array('label'=>'Heure de début','attr'=>array('class'=>'timepicker')))
            ->add('end',TextType::class,array('label'=>'Heure de fin','attr'=>array('class'=>'timepicker')))
            ->add('maxShiftersNb',IntegerType::class,array('label'=>'Nombre maximum de bénévoles','constraints' => array(new NotBlank())));
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Period'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_period';
    }


}
