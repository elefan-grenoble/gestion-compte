<?php

namespace App\Form;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Proxy;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user): void {
            $form = $event->getForm();
            $userData = $event->getData();

            if ($user->hasRole('ROLE_SUPER_ADMIN')) {
                $form->add('giver', EntityType::class, [
                    'class' => Membership::class,
                    'choice_label' => function (Membership $membership) {
                        $mainBeneficiary = $membership->getMainBeneficiary();
                        if ($mainBeneficiary) {
                            return $mainBeneficiary;
                        }

                        return $membership->getMemberNumber();

                    },
                    'label' => 'Utilisateur donnant la procuration',
                    'required' => false]);
                $form->add('owner', EntityType::class, [
                    'class' => Beneficiary::class,
                    'label' => 'beneficiaire acceptant la procuration',
                    'required' => false,
                ]);
            } else {
                if ($userData && $userData->getOwner()) {
                    $form->add('owner', EntityType::class, [
                        'class' => Beneficiary::class,
                        'choices' => [$userData->getOwner()],
                        'choice_label' => 'public_display_name',
                        'label' => 'beneficiaire acceptant la procuration']);
                } else {
                    $form->add('owner', EntityType::class, [
                        'class' => Beneficiary::class,
                        'choices' => $user->getBeneficiary()->getMembership()->getBeneficiaries(),
                        'label' => 'beneficiaire présent acceptant la procuration']);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Proxy::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_proxy';
    }
}
