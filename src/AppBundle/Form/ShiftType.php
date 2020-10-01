<?php

namespace AppBundle\Form;

use AppBundle\Entity\Shift;
use AppBundle\Repository\JobRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ShiftType extends AbstractType implements DataMapperInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date', TextType::class, array('label' => 'Date', 'attr' => array('class' => 'datepicker')))
            ->add('start', TextType::class, array('label' => 'Heure de début', 'attr' => array('class' => 'timepicker')))
            ->add('end', TextType::class, array('label' => 'Heure de fin', 'attr' => array('class' => 'timepicker')))
            ->add('formation', EntityType::class, array(
                'label' => 'Formation',
                'class' => 'AppBundle:Formation',
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
            ])
            ->add('job', EntityType::class, array(
                'label' => 'Type',
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
            ))
            ->setDataMapper($this);
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
        $forms['date']->setData($data->getStart());
        $forms['start']->setData($data->getStart());
        $forms['end']->setData($data->getEnd());
        $forms['job']->setData($data->getJob());
        $forms['formation']->setData($data->getFormation());
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
        $data->setFormation($forms['formation']->getData());
    }
}
