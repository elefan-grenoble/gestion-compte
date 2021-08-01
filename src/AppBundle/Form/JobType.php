<?php

namespace AppBundle\Form;

use AppBundle\Entity\Job;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name',TextType::class,array('label'=>'Nom du poste de bénévolat'))
            ->add('min_shifter_alert', IntegerType::class, array('label' => 'Nombre minimum de bénévoles inscrits sur le créneau pour ne pas envoyer d\'alerte', 'required' => true, 'data' => 2, 'empty_data' => 2))
            ->add('color',TextType::class,array('label'=>'Couleur des créneaux dans le planning'))
            ->add('description', MarkdownEditorType::class, array('label' => 'Description', 'required' => false, 'empty_data' => ''))
            ->add('enabled',CheckboxType::class,array('required' => false, 'label'=>'Poste activé'));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Job::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_job';
    }


}
