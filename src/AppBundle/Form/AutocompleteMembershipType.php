<?php

namespace AppBundle\Form;

use AppBundle\Form\DataTransformer\MembershipToStringTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class AutocompleteMembershipType extends AbstractType
{

    private $transformer;

    public function __construct(MembershipToStringTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'block_prefix' => 'autocomplete_membership',
            'attr' => ['class' => 'autocomplete'],
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
