<?php
// src/AppBundle/Form/RegistrationType.php

namespace AppBundle\Form;

use AppBundle\Entity\Registration;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use AppBundle\Form\AddressType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RegistrationType extends AbstractType
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

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
            // ... adding the name field if needed
            $form = $event->getForm();
            $registration = $event->getData();

            $form->add('date', DateType::class,array('label' => [
                'dat' => 'Jour',
                'year' => 'Année',
                'month' => 'Mois',
            ],'placeholder' => [
                'year' => 'Année',
                'month' => 'Mois',
            ],
                'years' => range(2016, date('Y')),'disabled' => !($user->hasRole('ROLE_ADMIN')||$user->hasRole('ROLE_SUPER_ADMIN'))))
                ->add('amount', TextType::class, array('label' => 'Montant','attr'=>array('placeholder'=>'15')));

            if (!$registration->getRegistrar() && ($user->hasRole('ROLE_SUPER_ADMIN'))){
                $form->add('registrar',EntityType::class,array(
                    'label' => 'Enregistré par',
                    'class' => 'AppBundle:User',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('u')
                        ->orderBy('u.username', 'ASC');
                    },
                    'choice_label' => 'username',
                ));
            }else{
                $form->add('registrar',EntityType::class,array('label' => 'Enregistré par','class' => 'AppBundle:User','choice_label' => 'username', 'attr'=>array('disabled' => true)));
            }



            $form->add('mode', ChoiceType::class, array('choices'  => array(
                    'Espèce' => Registration::TYPE_CASH,
                    'Chèque' => Registration::TYPE_CHECK,
                    'Cairn' => Registration::TYPE_LOCAL,
                    'CB' => Registration::TYPE_CREDIT_CARD,
                ),'label' => 'Mode de réglement')) //todo, make it dynamic
                ->add('submit', SubmitType::class, array('label' => 'Enregistrer','attr' => array('class' => 'btn')));
        });

    }
}