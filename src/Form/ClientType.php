<?php

namespace App\Form;

use OAuth2\OAuth2;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('urls', TextType::class, array('label' => 'return url(s)','attr' => array(
                'placeholder' => 'https://www.example.com/a,https://www.example.com/b'
            )))
            ->add('grant_types', ChoiceType::class,array('choices'  => array(
                OAuth2::GRANT_TYPE_AUTH_CODE => OAuth2::GRANT_TYPE_AUTH_CODE,
                OAuth2::GRANT_TYPE_IMPLICIT => OAuth2::GRANT_TYPE_IMPLICIT,
                OAuth2::GRANT_TYPE_USER_CREDENTIALS => OAuth2::GRANT_TYPE_USER_CREDENTIALS,
//                'lelefan user' => 'http://membres.lelefan.local/grants/user',
                OAuth2::GRANT_TYPE_CLIENT_CREDENTIALS => OAuth2::GRANT_TYPE_CLIENT_CREDENTIALS,
                OAuth2::GRANT_TYPE_REFRESH_TOKEN => OAuth2::GRANT_TYPE_REFRESH_TOKEN,
                OAuth2::GRANT_TYPE_EXTENSIONS => OAuth2::GRANT_TYPE_EXTENSIONS),'multiple'=>true))
            ->add('service',EntityType::class, array(
                'class' => 'App:Service',
                'choice_label'     => 'name',
                'multiple'     => false,
                'required' => true,
                'label'=>'Service'
            ));
    }

}
