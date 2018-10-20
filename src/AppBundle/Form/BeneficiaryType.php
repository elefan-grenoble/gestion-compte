<?php

namespace AppBundle\Form;

use AppBundle\Entity\User;
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

class BeneficiaryType extends AbstractType
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
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
            ->add('email',EmailType::class,array('constraints' => array( new NotBlank(), new Email()),'label'=>'Courriel', 'mapped' => false))
            ->add('lastname', TextType::class, array('constraints' => array(new NotBlank()), 'label' => 'Nom de famille'))
            ->add('firstname', TextType::class, array('constraints' => array(new NotBlank()), 'label' => 'Prénom'))
            ->add('phone', TextType::class, array('constraints' => array(), 'label' => 'Téléphone', 'required' => false))
            ->add('address', AddressType::class, array('label' => ' '));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user) {
            $form = $event->getForm();
            if ($user->hasRole('ROLE_USER_MANAGER') || $user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_SUPER_ADMIN')) {
                $form->add('commissions', EntityType::class, array(
                    'class' => 'AppBundle:Commission',
                    'placeholder' => '--- Commissions ---',
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => false,
                    'label' => 'Commission(s)'
                ));
                $form->add('formations', EntityType::class, array(
                    'class' => 'AppBundle:Formation',
                    'placeholder' => '--- Formations ---',
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => false,
                    'label' => 'Formation(s)'
                ));
            } else if ($user->getBeneficiary() && count($user->getBeneficiary()->getOwnedCommissions())) {
                $form->add('commissions', EntityType::class, array(
                    'class' => 'AppBundle:Commission',
                    'placeholder' => '--- Commissions ---',
                    'choices' => $user->getBeneficiary()->getOwnedCommissions(),
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => true,
                    'label' => 'Commission(s) / College(s)'
                ));
            }
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $beneficiary = $event->getData();
            if ($beneficiary && $beneficiary->getUser()) {
                $event->getForm()->get('email')->setData($beneficiary->getUser()->getEmail());
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $beneficiary = $event->getForm()->getData();
            if (!$beneficiary->getUser()) {
                $beneficiary->setUser(new User());
            }
            $beneficiary->getUser()->setEmail($event->getForm()->get('email')->getData());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Beneficiary'
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
