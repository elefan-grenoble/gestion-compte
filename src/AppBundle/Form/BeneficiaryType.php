<?php

namespace AppBundle\Form;

use AppBundle\Entity\Commission;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
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
            ->add('lastname',TextType::class,array('constraints' => array( new NotBlank()), 'label'=>'Nom de famille'))
            ->add('firstname',TextType::class,array('constraints' => array( new NotBlank()),'label'=>'Prénom'))
            ->add('email',EmailType::class,array('constraints' => array( new NotBlank(), new Email()),'label'=>'Courriel'))
            ->add('phone',TextType::class,array('constraints' => array(),'label'=>'Téléphone','required' => false));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user) {
            $form = $event->getForm();
            if ($user->hasRole('ROLE_USER_MANAGER')||$user->hasRole('ROLE_ADMIN')||$user->hasRole('ROLE_SUPER_ADMIN')){
                $form->add('commissions',EntityType::class, array(
                    'class' => 'AppBundle:Commission',
                    'placeholder' => '--- Commissions ---',
                    'choice_label'     => 'name',
                    'multiple'     => true,
                    'required' => false,
                    'label'=>'Commission(s)'
                ));
                $form->add('roles',EntityType::class, array(
                    'class' => 'AppBundle:Role',
                    'placeholder' => '--- Roles ---',
                    'choice_label'     => 'name',
                    'multiple'     => true,
                    'required' => false,
                    'label'=>'Role(s)'
                ));
            }else if(count($user->getOwnedCommissions())){
                $form->add('commissions',EntityType::class, array(
                    'class' => 'AppBundle:Commission',
                    'placeholder' => '--- Commissions ---',
                    'choices' => $user->getOwnedCommissions(),
                    'choice_label'     => 'name',
                    'multiple'     => true,
                    'required' => true,
                    'label'=>'Commission(s) / College(s)'
                ));
            }
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
