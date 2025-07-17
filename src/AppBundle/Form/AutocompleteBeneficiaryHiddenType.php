<?php

namespace AppBundle\Form;

use AppBundle\Form\DataTransformer\BeneficiaryToStringTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class AutocompleteBeneficiaryHiddenType extends AbstractType
{

    private $transformer;

    public function __construct(BeneficiaryToStringTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function getParent()
    {
        return HiddenType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer($this->transformer);
    }

}
