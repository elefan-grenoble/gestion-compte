<?php

namespace App\Form;

use App\Entity\Beneficiary;
use App\Entity\User;
use App\EventListener\BeneficiaryInitializationSubscriber;
use App\Repository\CommissionRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
    private $use_fly_and_fixed;
    private $fly_and_fixed_entity_flying;
    private $tokenStorage;
    private $validator;
    private $beneficiaryInitializationSubscriber;

    public function __construct(bool $use_fly_and_fixed, string $fly_and_fixed_entity_flying, TokenStorageInterface $tokenStorage, ValidatorInterface $validator, BeneficiaryInitializationSubscriber $beneficiaryInitializationSubscriber)
    {
        $this->use_fly_and_fixed = $use_fly_and_fixed;
        $this->fly_and_fixed_entity_flying = $fly_and_fixed_entity_flying;
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
            if (is_object($user) && ($user->hasRole('ROLE_USER_MANAGER') || $user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_SUPER_ADMIN'))) {
                if ($this->use_fly_and_fixed && $this->fly_and_fixed_entity_flying == 'Beneficiary') {
                    $form->add('flying', ChoiceType::class, array(
                        'choices'  => array(
                            'Oui' => true,
                            'Non' => false,
                        ),
                        'required' => true,
                        'label' => 'Equipe volante'
                    ));
                } else {
                    $form->add('flying', HiddenType::class, [
                        'data' => '0',
                        'label' => false
                    ]);
                }
                $form->add('commissions', EntityType::class, array(
                    'class' => 'App:Commission',
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => false,
                    'label' => 'Commission(s)'
                ));
                $form->add('formations', EntityType::class, array(
                    'class' => 'App:Formation',
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => false,
                    'label' => 'Formation(s)',
                    'query_builder' => function(FormationRepository $repository) {
                        $qb = $repository->createQueryBuilder('f');
                        return $qb->orderBy('f.name', 'ASC');
                    }
                ));
            } else if (is_object($user) && ($user->getBeneficiary() && count($user->getBeneficiary()->getOwnedCommissions()))) {
                $form->add('commissions', EntityType::class, array(
                    'class' => 'App:Commission',
                    'choices' => $user->getBeneficiary()->getOwnedCommissions(),
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => true,
                    'label' => 'Commission(s) / College(s)',
                    'query_builder' => function(CommissionRepository $repository) {
                        $qb = $repository->createQueryBuilder('c');
                        return $qb->orderBy('c.name', 'ASC');
                    }
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
        return 'App_beneficiary';
    }
}
