<?php

namespace AppBundle\Form;

use AppBundle\Entity\CodeDevice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class CodeType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('description', null, array('label'=>'Description','constraints' => array(
                        new NotBlank(),
                    )));
        if ($options['codedevice_type'] == 'igloohome') {
            $builder->add('start_date', DateTimeType::class, [
                        'html5' => false,
                        'date_widget' => 'single_text',
                        'time_widget' => 'single_text',
                        'model_timezone' => 'Europe/Paris',
                        'view_timezone'  => 'Europe/Paris'
                    ])
                    ->add('end_date', DateTimeType::class, [
                        'html5' => false,
                        'date_widget' => 'single_text',
                        'time_widget' => 'single_text',
                        'model_timezone' => 'Europe/Paris',
                        'view_timezone'  => 'Europe/Paris'
                    ]);
        } else {
            $builder->add('value', null, array('label'=>'Code'));
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $code = $event->getData();
            $form = $event->getForm();

            // checks if the Code object is "new"
            // If no data is passed to the form, the data is "null".
            if ((!$code || null === $code->getId()) && $options['codedevice_type'] != 'igloohome') {
                $form->add('generate_random_value', CheckboxType::class, array(
                    'label' => 'Générer une combinaison aléatoire ?',
                    'mapped' => false,
                    'required' => false,
                    'attr' => array('class' => 'filled-in')))
                     ->add('deactivate_old_codes', CheckboxType::class, array(
                    'label' => 'Désactiver les anciens codes ?',
                    'mapped' => false,
                    'required' => false,
                    'attr' => array('class' => 'filled-in')));
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Code',
            'codedevice_type' => 'other',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_code';
    }


}
