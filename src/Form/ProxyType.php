<?php

namespace App\Form;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Proxy;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProxyType extends AbstractType
{
    /**
     * @var TokenStorageInterface
     */
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
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if (!$user) {
            throw new \LogicException(
                'cannot be used without an authenticated user!'
            );
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user) {
            $form = $event->getForm();
            $userData = $event->getData();

            if ($user->hasRole('ROLE_SUPER_ADMIN')){
                $form->add('giver',EntityType::class,array(
                    'class' => Membership::class,
                    'choice_label' => function (Membership $membership) {
                        $mainBeneficiary = $membership->getMainBeneficiary();
                        if ($mainBeneficiary) {
                            return $mainBeneficiary;
                        } else {
                            return $membership->getMemberNumber();
                        }
                    },
                    'label'=>'Utilisateur donnant la procuration',
                    'required' => false));
                $form->add('owner',EntityType::class,array(
                    'class' => Beneficiary::class,
                    'label'=>'beneficiaire acceptant la procuration',
                    'required' => false
                ));
            }else{
                if ($userData && $userData->getOwner()){
                    $form->add('owner',EntityType::class,array(
                        'class' => Beneficiary::class,
                        'choices' => array($userData->getOwner()),
                        'choice_label' => 'public_display_name',
                        'label'=>'beneficiaire acceptant la procuration'));
                }else{
                    $form->add('owner',EntityType::class,array(
                        'class' => Beneficiary::class,
                        'choices' => $user->getBeneficiary()->getMembership()->getBeneficiaries(),
                        'label'=>'beneficiaire présent acceptant la procuration'));
                }
            }
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Proxy::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_proxy';
    }


}
