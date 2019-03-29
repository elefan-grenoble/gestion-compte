<?php

namespace AppBundle\Form;

use AppBundle\Form\DataTransformer\BeneficiaryToStringTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class AutocompleteBeneficiaryType extends AbstractType
{

    private $transformer;

    public function __construct(BeneficiaryToStringTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function getBlockPrefix()
    {
        return 'autocomplete_beneficiary';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'invalid_message' => 'The selected data does not match any beneficiary',
        ]);
    }

    public function getParent()
    {
        return TextType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer($this->transformer);
    }

}
