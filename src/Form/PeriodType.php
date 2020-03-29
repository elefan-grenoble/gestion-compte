<?php

namespace App\Form;

use App\Entity\Period;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Repository\JobRepository;

class PeriodType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('day_of_week', ChoiceType::class, array('label' => 'Jour de la semaine', 'choices' => array(
                "Lundi" => 0,
                "Mardi" => 1,
                "Mercredi" => 2,
                "Jeudi" => 3,
                "Vendredi" => 4,
                "Samedi" => 5,
                "Dimanche" => 6,
            )))
            ->add('start', TextType::class, array('label' => 'Heure de début', 'attr' => array('class' => 'timepicker')))
            ->add('end', TextType::class, array('label' => 'Heure de fin', 'attr' => array('class' => 'timepicker')))
            ->add('job', EntityType::class, array(
                'label' => 'Poste',
                'class' => 'App:Job',
                'choice_label'=> 'name',
                'multiple'     => false,
                'required' => true,
                'query_builder' => function(JobRepository $repository) {
                    $qb = $repository->createQueryBuilder('j');
                    return $qb
                        ->where($qb->expr()->eq('j.enabled', '?1'))
                        ->setParameter('1', '1')
                        ->orderBy('j.name', 'ASC');
                }
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Period::class
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
