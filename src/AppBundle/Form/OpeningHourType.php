<?php

namespace AppBundle\Form;

use AppBundle\Entity\OpeningHour;
use AppBundle\Entity\Period;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Repository\JobRepository;

class OpeningHourType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dayOfWeek', ChoiceType::class, array('label' => 'Jour de la semaine', 'choices' => Period::DAYS_OF_WEEK_LIST_WITH_INT))
            ->add('start', TextType::class, array('label' => 'Heure de début', 'attr' => array('class' => 'timepicker')))
            ->add('end', TextType::class, array('label' => 'Heure de fin', 'attr' => array('class' => 'timepicker')));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => OpeningHour::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_opening_hour';
    }
}
