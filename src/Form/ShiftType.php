<?php

namespace App\Form;

use App\Entity\Shift;
use App\Repository\JobRepository;
use App\Form\DataTransformer\JobToNumberTransformer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ShiftType extends AbstractType implements DataMapperInterface
{

    private $transformer;

    public function __construct(JobToNumberTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options['only_add_formation']) {
            $builder
                ->add('date', TextType::class, array('label' => 'Date', 'attr' => array('class' => 'datepicker')))
                ->add('start', TextType::class, array('label' => 'Heure de début', 'attr' => array('class' => 'timepicker')))
                ->add('end', TextType::class, array('label' => 'Heure de fin', 'attr' => array('class' => 'timepicker')))
                ->add('job', EntityType::class, array(
                    'label' => 'Type',
                    'class' => 'App:Job',
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
                ))
                ->setDataMapper($this);
        } else {
            $builder
                ->add('date', HiddenType::class)
                ->add('start', HiddenType::class)
                ->add('end', HiddenType::class)
                ->add('job', HiddenType::class);

            $builder->get("job")
                    ->addModelTransformer($this->transformer);
            $builder
                ->setDataMapper($this);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $shift = $event->getData();
            $form = $event->getForm();

            // checks if the Shift object is "new"
            // If no data is passed to the form, the data is "null".
            if (!$shift || null === $shift->getId() || $options['only_add_formation']) {
                $form->add('formation', EntityType::class, array(
                    'label' => 'Formation',
                    'class' => 'App:Formation',
                    'choice_label' => 'name',
                    'multiple' => false,
                    'required' => false
                ))
                ->add('number', IntegerType::class, [
                    'label' => 'Nombre de postes disponibles',
                    'required' => true,
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

    /**
     * @param Shift|null $data
     */
    public function mapDataToForms($data, $forms)
    {
        // there is no data yet, so nothing to prepopulate
        if (null === $data) {
            return;
        }

        // invalid data type
        if (!$data instanceof Shift) {
            throw new UnexpectedTypeException($data, Shift::class);
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        // initialize form field values
        if (!is_null($data->getStart())) {
            $forms['date']->setData($data->getStart()->format('Y-m-d'));
            $forms['start']->setData($data->getStart()->format('H:i'));
        }
        if (!is_null($data->getEnd())) {
            $forms['end']->setData($data->getEnd()->format('H:i'));
        }
        $forms['job']->setData($data->getJob());
    }

    public function mapFormsToData($forms, &$data)
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        $date = new \DateTime($forms['date']->getData());
        $start = new \DateTime($forms['start']->getData());
        $end = new \DateTime($forms['end']->getData());

        $year = intval($date->format('Y'));
        $month = intval($date->format('n'));
        $day = intval($date->format('d'));

        $start->setDate($year, $month, $day);
        $end->setDate($year, $month, $day);

        $data->setStart($start);
        $data->setEnd($end);
        $data->setJob($forms['job']->getData());
        if (array_key_exists('formation', $forms)) {
            $data->setFormation($forms['formation']->getData());
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'only_add_formation' => false,
        ]);
    }
}
