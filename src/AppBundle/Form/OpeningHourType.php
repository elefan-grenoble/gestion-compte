<?php

namespace AppBundle\Form;

use AppBundle\Entity\OpeningHour;
use AppBundle\Entity\Period;
use AppBundle\Repository\OpeningHourKindRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OpeningHourType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dayOfWeek', ChoiceType::class, array(
                'label' => 'Jour de la semaine',
                'choices' => Period::DAYS_OF_WEEK_LIST_WITH_INT
            ))
            ->add('start', TextType::class, array(
                'label' => 'Heure de début',
                'required' => false,
                'attr' => array('class' => 'timepicker')
            ))
            ->add('end', TextType::class, array(
                'label' => 'Heure de fin',
                'required' => false,
                'attr' => array('class' => 'timepicker')
            ))
            ->add('closed', CheckboxType::class, array(
                'required' => false,
                'data' => false,
                'label' => 'Fermé ?',
                'attr' => array('class' => 'filled-in')
            ))
            ->add('kind', EntityType::class, array(
                'label' => 'Type d\'horaire d\'ouverture',
                'class' => 'AppBundle:OpeningHourKind',
                'choice_label' => 'name',
                'multiple' => false,
                'query_builder' => function (OpeningHourKindRepository $repository) {
                    return $repository->createQueryBuilder('ohk')
                        ->orderBy('ohk.id', 'ASC');
                },
            ));
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
