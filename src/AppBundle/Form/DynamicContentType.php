<?php

namespace AppBundle\Form;

use AppBundle\Entity\DynamicContent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DynamicContentType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, array('label' => 'Nom du contenu', 'required' => true))
            ->add('description', TextType::class, array('label' => 'Description', 'required' => true))
            ->add('type', ChoiceType::class, array('label' => 'Type', 'required' => true, 'choices' => array(
                "Général" => "general",
                "Email" => "email",
                "Notification" => "notification",
            )))
            ->add('content', MarkdownEditorType::class, array('label' => 'Contenu', 'required' => false));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => DynamicContent::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_dynamic_content';
    }


}
