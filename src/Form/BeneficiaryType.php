<?php

namespace App\Form;

use App\Entity\Beneficiary;
use App\Entity\User;
use App\EventListener\BeneficiaryInitializationSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BeneficiaryType extends AbstractType
{
    private $tokenStorage;
    /**
     * @var ValidatorInterface
     */
    private $validator;
    /**
     * @var EntityManager
     */
    private $em;
    /**
     * @var BeneficiaryInitializationSubscriber
     */
    private $beneficiaryInitializationSubscriber;

    public function __construct(TokenStorageInterface $tokenStorage, ValidatorInterface $validator, BeneficiaryInitializationSubscriber $beneficiaryInitializationSubscriber)
    {
        $this->tokenStorage = $tokenStorage;
        $this->validator = $validator;
        $this->beneficiaryInitializationSubscriber = $beneficiaryInitializationSubscriber;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // grab the user, do a quick sanity check that one exists
        $user = $this->tokenStorage->getToken()->getUser();
        if (!$user) {
            throw new \LogicException(
                'cannot be used without an authenticated user!'
            );
        }

        $builder
            ->add('user', UserType::class)
            ->add('lastname', TextType::class, array('label' => 'Nom de famille'))
            ->add('firstname', TextType::class, array('label' => 'Prénom'))
            ->add('phone', TextType::class, array('label' => 'Téléphone', 'required' => false))
            ->add('address', AddressType::class, array('label' => false));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user) {
            $form = $event->getForm();
            if (is_object($user)&&($user->hasRole('ROLE_USER_MANAGER') || $user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_SUPER_ADMIN'))) {
                $form->add('commissions', EntityType::class, array(
                    'class' => 'App:Commission',
                    'placeholder' => '--- Commissions ---',
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => false,
                    'label' => 'Commission(s)'
                ));
                $form->add('formations', EntityType::class, array(
                    'class' => 'App:Formation',
                    'placeholder' => '--- Formations ---',
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => false,
                    'label' => 'Formation(s)'
                ));
            } else if (is_object($user) && ($user->getBeneficiary() && count($user->getBeneficiary()->getOwnedCommissions()))) {
                $form->add('commissions', EntityType::class, array(
                    'class' => 'App:Commission',
                    'placeholder' => '--- Commissions ---',
                    'choices' => $user->getBeneficiary()->getOwnedCommissions(),
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => true,
                    'label' => 'Commission(s) / College(s)'
                ));
            }
        });

        $builder->addEventSubscriber($this->beneficiaryInitializationSubscriber);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Beneficiary::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_beneficiary';
    }


}
