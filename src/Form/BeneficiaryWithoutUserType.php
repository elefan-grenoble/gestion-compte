<?php

namespace App\Form;

use App\EventListener\BeneficiaryInitializationSubscriber;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BeneficiaryWithoutUserType extends BeneficiaryType
{
    public function __construct(TokenStorageInterface $tokenStorage, ValidatorInterface $validator, BeneficiaryInitializationSubscriber $beneficiaryInitializationSubscriber)
    {
         parent::__construct($tokenStorage, $validator, $beneficiaryInitializationSubscriber);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder->remove('user');
    }
}