<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CodeDeviceType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', null, ['label' => 'Nom de l\'équipement'])
                ->add('type', ChoiceType::class, ['placeholder' => 'Choose an option', 'choices'  => ['Igloohome' => 'igloohome', 'Autre' => 'other'], 'attr' =>  array('onchange' => 'displayIgloohomeFields();')])
                ->add('igloohome_api_key', null, ['label' => 'Igloohome API Key'])
                ->add('igloohome_lock_id', null, ['label' => 'Igloohome Lock Id'])
                ->add('description', TextareaType::class, ['label' => 'Description', 'attr' => ['class' => 'materialize-textarea']])
                ->add('enabled', CheckboxType::class, [
                    'label'=>'Codes visibles par les membres ?',
                    'required' => false,
                    'attr' => array('class' => 'filled-in')
                ]);

    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\CodeDevice'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_codedevice';
    }


}
