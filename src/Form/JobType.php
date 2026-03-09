<?php

namespace App\Form;

use App\Entity\Job;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom du poste de bénévolat'])
            ->add('min_shifter_alert', IntegerType::class, ['label' => 'Nombre minimum de bénévoles inscrits sur le créneau pour ne pas envoyer d\'alerte', 'required' => true, 'data' => 2, 'empty_data' => 2])
            ->add('color', TextType::class, ['label' => 'Couleur des créneaux dans le planning'])
            ->add('description', MarkdownEditorType::class, ['label' => 'Description', 'required' => false, 'empty_data' => ''])
            ->add('url', TextType::class, ['label' => 'Lien vers une documentation', 'required' => false])
            ->add('enabled', CheckboxType::class, ['label' => 'Poste activé', 'required' => false, 'attr' => ['class' => 'filled-in']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Job::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_job';
    }
}
