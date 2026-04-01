<?php

namespace App\Form;

use App\Entity\OpeningHour;
use App\Entity\Period;
use App\Repository\OpeningHourKindRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OpeningHourType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dayOfWeek', ChoiceType::class, [
                'label' => 'Jour de la semaine',
                'choices' => Period::DAYS_OF_WEEK_LIST_WITH_INT,
            ])
            ->add('start', TextType::class, [
                'label' => 'Heure de début',
                'required' => false,
                'attr' => ['class' => 'timepicker'],
            ])
            ->add('end', TextType::class, [
                'label' => 'Heure de fin',
                'required' => false,
                'attr' => ['class' => 'timepicker'],
            ])
            ->add('closed', CheckboxType::class, [
                'required' => false,
                'label' => 'Fermé ?',
                'attr' => ['class' => 'filled-in'],
            ])
            ->add('kind', EntityType::class, [
                'label' => 'Type d\'horaire d\'ouverture',
                'class' => 'App:OpeningHourKind',
                'choice_label' => 'name',
                'multiple' => false,
                'query_builder' => function (OpeningHourKindRepository $repository) {
                    return $repository->createQueryBuilder('ohk')
                        ->orderBy('ohk.id', 'ASC')
                    ;
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => OpeningHour::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_opening_hour';
    }
}
