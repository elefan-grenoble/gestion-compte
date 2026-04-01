<?php

namespace App\Form;

use App\Entity\EmailTemplate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'required' => true])
            ->add('description', TextType::class, ['label' => 'Description', 'required' => false])
            ->add('content', MarkdownEditorType::class, ['label' => 'Contenu', 'required' => true])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => EmailTemplate::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_email_template';
    }
}
