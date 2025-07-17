<?php

namespace AppBundle\Service;

use AppBundle\Form\AutocompleteBeneficiaryType;
use AppBundle\Repository\JobRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class PeriodFormHelper
{
    public function getFilterForm($formBuilder, $withBeneficiaryField = false)
    {
        $formBuilder
            ->add('job', EntityType::class, array(
                'label' => 'Type de créneau',
                'class' => 'AppBundle:Job',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
                'query_builder' => function(JobRepository $repository) {
                    $qb = $repository->createQueryBuilder('j');
                    return $qb
                        ->where($qb->expr()->eq('j.enabled', '?1'))
                        ->setParameter('1', '1')
                        ->orderBy('j.name', 'ASC');
                }
            ))
            ->add('week', ChoiceType::class, array(
                'label' => 'Semaine',
                'required' => false,
                'choices' => array(
                    'A' => 'A',
                    'B' => 'B',
                    'C' => 'C',
                    'D' => 'D',
                ),
            ));

        // admin filter form has beneficiary field + more complex filling field
        if ($withBeneficiaryField) {
            $formBuilder
                ->add('beneficiary', AutocompleteBeneficiaryType::class, array(
                    'label' => 'Bénéficiaire',
                    'required' => false,
                ))
                ->add('filling', ChoiceType::class, array(
                    'label' => 'Remplissage',
                    'required' => false,
                    'choices' => array(
                        'Complet' => 'full',
                        'Partiel' => 'partial',
                        'Vide' => 'empty',
                        'Problématique' => 'problematic'  // additional
                    ),
                ));
        } else {
            $formBuilder
                ->add('filling', ChoiceType::class, array(
                    'label' => 'Remplissage',
                    'required' => false,
                    'choices' => array(
                        'Complet' => 'full',
                        'Partiel' => 'partial',
                        'Vide' => 'empty',
                    ),
                ));
        }

        $formBuilder
            ->add('submit', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'Filtrer')
            ));

        return $formBuilder->getForm();
    }

    public function createFilterForm($formBuilder, $defaults, $withBeneficiaryField = false) {
        $form = $this->getFilterForm($formBuilder, $withBeneficiaryField);
        foreach ($defaults as $k => $v) {
            $form->get($k)->setData($v);
        }
        return $form;
    }
}
