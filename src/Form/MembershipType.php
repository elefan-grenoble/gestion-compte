<?php

namespace App\Form;

use App\Entity\Membership;
use App\Entity\Registration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MembershipType extends AbstractType
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

        $builder->add('mainBeneficiary', BeneficiaryType::class);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user): void {
            $form = $event->getForm();

            /** @var Membership $userData */
            $userData = $event->getData();

            if (is_object($user) && ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_SUPER_ADMIN'))) {
                $form->add('member_number', IntegerType::class, ['label' => 'Numéro d\'adhérent']);
            } else {
                $form->add('member_number', IntegerType::class, ['label' => 'Numéro d\'adhérent', 'disabled' => true]);
            }

            if ($userData && !$userData->getId() && $userData->getLastRegistration()->getAmount() === null) {
                $form->add('lastRegistration', RegistrationType::class, ['label' => ' ', 'data_class' => Registration::class]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Membership::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_membership';
    }
}
