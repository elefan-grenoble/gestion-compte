<?php

namespace App\Form;

use App\Entity\Membership;
use App\Entity\Registration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MemberType extends AbstractType
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

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user) {
            $form = $event->getForm();
            $memberData = $event->getData();

            if (is_object($user) && ($user->hasRole('ROLE_ADMIN')||$user->hasRole('ROLE_SUPER_ADMIN'))){
                $form->add('member_number',IntegerType::class, array('label'=> 'Numéro d\'adhérent'));
            }else{
                $form->add('member_number',IntegerType::class, array('label'=> 'Numéro d\'adhérent','disabled' => true));
            }

            if (is_object($user)){
                if ($user->hasRole('ROLE_USER_MANAGER')||$user->hasRole('ROLE_ADMIN')||$user->hasRole('ROLE_SUPER_ADMIN')){
                    if ($memberData && $memberData->getId()) { //in not new
                        $form->add('withdrawn', CheckboxType::class, array('label' => 'Compte fermé', 'required' => false));
                        $form->add('frozen', CheckboxType::class, array('label' => 'Compte gelé', 'required' => false));
                    }
                }
            }

            $form->add('mainBeneficiary', BeneficiaryType::class,array('label'=>' '));

            if ($memberData && !$memberData->getId()){
                $form->add('lastRegistration', RegistrationType::class,array('label'=>' ','data_class'=>Registration::class));
            }

        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Membership::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_membership';
    }


}
