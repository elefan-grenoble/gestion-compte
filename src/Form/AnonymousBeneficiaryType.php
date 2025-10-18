<?php

namespace App\Form;

use App\Entity\AnonymousBeneficiary;
use App\Entity\Registration;
use App\Validator\Constraints\UniqueEmail;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AnonymousBeneficiaryType extends AbstractType
{
    private $local_currency_name;
    private $tokenStorage;

    public function __construct(string $local_currency_name, TokenStorageInterface $tokenStorage)
    {
        $this->local_currency_name = $local_currency_name;
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
            ->add('email', EmailType::class, array(
                'label' => 'Email du nouveau membre'
            ))
            ->add('join_to', AutocompleteBeneficiaryType::class, array(
                'label' => 'Email ou nom du compte parent',
                'required' => false
            ))
            ->add('beneficiaries_emails',CollectionType::class, array(
                'label' => false, // 'Email bénéficiaire',
                'required' => false,
                'entry_type' => EmailType::class,
                'entry_options' => array(
                    'label' => 'Email bénéficiaire',
                    'attr' => array('placehoder'=>'email@domain.fr'),
                    'required' => false,
                    'constraints' => [new UniqueEmail()]
                ),
                'allow_add'=>true
            ))
            ->add('amount', TextType::class, array(
                'label' => 'Montant',
                'attr'=> array('placeholder' => '15'),
                'required' => false
            ))
            ->add('mode', ChoiceType::class, array(  // todo, make it dynamic
                'label' => 'Mode de réglement',
                'placeholder' => '',
                'required' => false,
                'choices' => array(
                    'Espèce' => Registration::TYPE_CASH,
                    'Chèque' => Registration::TYPE_CHECK,
                    $this->local_currency_name => Registration::TYPE_LOCAL,
                    'Helloasso' => Registration::TYPE_HELLOASSO,
                )
            ));

        $builder->get('beneficiaries_emails')->addModelTransformer(new CallbackTransformer(
            function ($beneficiariesAsString) {
                // transform the string to an array
                return explode(', ', $beneficiariesAsString);
            },
            function ($beneficiariesAsArray) {
                // transform the array back to a string
                return implode(', ', $beneficiariesAsArray);
            }
        ))
        ;

    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => AnonymousBeneficiary::class
        ));
    }
}
