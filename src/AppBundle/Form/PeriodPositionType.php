<?php

namespace AppBundle\Form;

use AppBundle\Entity\Formation;
use AppBundle\Entity\PeriodPosition;
use AppBundle\Entity\PeriodRoom;
use AppBundle\Entity\Role;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class PeriodPositionType extends AbstractType
{
    const WEEKA = 'A';
    const WEEKB = 'B';
    const WEEKC = 'C';
    const WEEKD = 'D';

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('week_cycle', ChoiceType::class, array(
                'label' => 'Cycle', 'choices' => array(
                    "Semaine A" => self::WEEKA,
                    "Semaine B" => self::WEEKB,
                    "Semaine C" => self::WEEKC,
                    "Semaine D" => self::WEEKD,
                ),
                'expanded'  => false,
                'multiple'  => true,
                'empty_data' => [self::WEEKA, self::WEEKB, self::WEEKC, self::WEEKD]
            ))
            ->add('formation', EntityType::class, array(
                'label'=>'Rôle necessaire',
                'choice_label' => 'name',
                'class' => Formation::class,
                'multiple' => false,
                'required' => false
            ));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $period_position = $event->getData();
            $form = $event->getForm();

            // checks if the PeriodPosition object is "new"
            // If no data is passed to the form, the data is "null".
           if (!$period_position || null === $period_position->getId()) {
                $form->add('nb_of_shifter', IntegerType::class, [
                    'label' => 'Nombre de postes disponibles',
                    'required' => true,
                    'mapped' => false,
                    'data' => 1,
                    'attr' => [
                        'min' => 1
                    ]
                ]);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => PeriodPosition::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_period_position';
    }


}
