<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserAdminType extends UserType
{
    public function __construct(TokenStorageInterface $tokenStorage)
    {
        parent::__construct($tokenStorage);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder
            ->add('username', TextType::class, array('label' => "Nom d'utilisateur"))
            ->add('password', PasswordType::class, array('label' => "Mot de passe", 'constraints' => [new NotBlank()]));
    }
}