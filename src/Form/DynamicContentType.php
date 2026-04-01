<?php

namespace App\Form;

use App\Entity\DynamicContent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DynamicContentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom du contenu', 'required' => true])
            ->add('description', TextType::class, ['label' => 'Description', 'required' => true])
            ->add('type', ChoiceType::class, ['label' => 'Type', 'required' => true, 'choices' => [
                'Général' => 'general',
                'Email' => 'email',
                'Notification' => 'notification',
            ]])
            ->add('content', MarkdownEditorType::class, ['label' => 'Contenu', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DynamicContent::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_dynamic_content';
    }
}
