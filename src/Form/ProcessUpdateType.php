<?php

namespace App\Form;

use App\Entity\ProcessUpdate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProcessUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre', 'required' => true])
            ->add('link', TextType::class, ['label' => 'Lien vers la procédure complète', 'required' => false])
            ->add('description', MarkdownEditorType::class, ['label' => 'Description', 'required' => true])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ProcessUpdate::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_process_update';
    }
}
