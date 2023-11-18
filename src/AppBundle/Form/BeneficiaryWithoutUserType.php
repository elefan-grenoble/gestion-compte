<?php

namespace AppBundle\Form;

use AppBundle\EventListener\BeneficiaryInitializationSubscriber;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BeneficiaryWithoutUserType extends BeneficiaryType
{
    private $use_fly_and_fixed;
    private $fly_and_fixed_entity_flying;

    public function __construct(bool $use_fly_and_fixed, string $fly_and_fixed_entity_flying, TokenStorageInterface $tokenStorage, ValidatorInterface $validator, BeneficiaryInitializationSubscriber $beneficiaryInitializationSubscriber)
    {
        parent::__construct($use_fly_and_fixed, $fly_and_fixed_entity_flying, $tokenStorage, $validator, $beneficiaryInitializationSubscriber);
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
