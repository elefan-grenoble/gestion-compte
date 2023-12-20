<?php

namespace App\Form;

use App\Entity\Formation;
use App\Entity\Period;
use App\Entity\PeriodPosition;
use App\Entity\PeriodRoom;
use App\Entity\Role;
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
    private $cycle_type;

    public function __construct($cycle_type)
    {
        $this->cycle_type = $cycle_type;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('week_cycle', ChoiceType::class, array(
                'label' => 'Cycle',
                'choices' => ($this->cycle_type == "abcd") ? Period::WEEK_CYCLE_CHOICE_LIST : [],
                'expanded' => false,
                'multiple' => true,
                // 'data' => ($this->cycle_type == 'abcd') ? null : [Period::WEEK_A]
            ))
            ->add('formation', EntityType::class, array(
                'label'=>'Formation necessaire',
                'choice_label' => 'name',
                'class' => Formation::class,
                'multiple' => false,
                'required' => false
            ));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $period_position = $event->getData();
            $form = $event->getForm();

            // checks if the PeriodPosition object is "new"
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
        return 'App_period_position';
    }
}
