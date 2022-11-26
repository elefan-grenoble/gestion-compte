<?php

namespace AppBundle\Form;

use AppBundle\Form\AutocompleteBeneficiaryHiddenType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class AutocompleteBeneficiaryCollectionType extends AbstractType
{

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'entry_type' => AutocompleteBeneficiaryHiddenType::class,
            'allow_add' => true,
            'prototype' => false,
            'block_prefix' => 'autocomplete_beneficiary_collection'
        ]);
    }

    public function getParent()
    {
        return CollectionType::class;
    }

}
