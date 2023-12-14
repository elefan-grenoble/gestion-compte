<?php

namespace AppBundle\Form;

use AppBundle\Entity\Shift;
use AppBundle\Form\JobHiddenType;
use AppBundle\Repository\JobRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

class ShiftType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options['only_add_formation']) {
            $builder
                ->add('start', DateTimeType::class, ['html5' => false, 'date_widget' => 'single_text', 'time_widget' => 'single_text'])
                ->add('end', DateTimeType::class, ['html5' => false, 'date_widget' => 'single_text', 'time_widget' => 'single_text',
                    'constraints' => [
                        new GreaterThan([
                            'propertyPath' => 'parent.all[start].data'
                        ])]])
                ->add('job', EntityType::class, array(
                    'label' => 'Poste',
                    'class' => 'AppBundle:Job',
                    'choice_label' => 'name',
                    'multiple' => false,
                    'required' => true,
                    'query_builder' => function(JobRepository $repository) {
                        $qb = $repository->createQueryBuilder('j');
                        return $qb
                            ->where($qb->expr()->eq('j.enabled', '?1'))
                            ->setParameter('1', '1')
                            ->orderBy('j.name', 'ASC');
                    }
                ));

        } else {
            $builder
                ->add('start', DateTimeType::class, ['html5' => false, 'date_widget' => 'single_text', 'time_widget' => 'single_text'])
                ->add('end', DateTimeType::class, ['html5' => false, 'date_widget' => 'single_text', 'time_widget' => 'single_text'])
                ->add('job', JobHiddenType::class);

        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $shift = $event->getData();
            $form = $event->getForm();

            // checks if the Shift object is "new"
            // If no data is passed to the form, the data is "null".
            if (!$shift || null === $shift->getId() || $options['only_add_formation']) {
                $form->add('formation', EntityType::class, array(
                    'label' => 'Formation',
                    'class' => 'AppBundle:Formation',
                    'choice_label' => 'name',
                    'multiple' => false,
                    'required' => false,
                    'query_builder' => function(FormationRepository $repository) {
                        $qb = $repository->createQueryBuilder('f');
                        return $qb->orderBy('f.name', 'ASC');
                    }
                ))
                ->add('number', IntegerType::class, [
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
    public function getBlockPrefix()
    {
        return 'appbundle_shift';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'only_add_formation' => false,
        ]);
    }
}
