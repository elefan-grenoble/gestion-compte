<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ServiceType extends AbstractType
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

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $userData = $event->getData();

            $form->add('name', TextType::class, ['label' => 'nom', 'required' => true])
                ->add('description', TextType::class, ['label' => 'Description'])
                ->add('slug', TextType::class, ['label' => 'nom court', 'required' => true])
                ->add('icon', TextType::class, ['label' => 'Icon name', 'required' => false])
                ->add('url', TextType::class, ['label' => 'Adresse web', 'required' => false])
            ;
            $form->add('logoFile', VichImageType::class, [
                'required' => false,
                'allow_delete' => true,
                'download_link' => true,
            ]);
            $form->add('public', CheckboxType::class, [
                'required' => false,
                'label' => 'Visible sur le menu ?',
                'attr' => ['class' => 'filled-in'],
            ]);
        });

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_service';
    }
}
