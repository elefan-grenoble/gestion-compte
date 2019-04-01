<?php

namespace AppBundle\Form;

use AppBundle\Entity\AnonymousBeneficiary;
use AppBundle\Entity\Commission;
use AppBundle\Entity\Registration;
use AppBundle\Validator\Constraints\UniqueEmail;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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

class AnonymousBeneficiaryType extends AbstractType
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
            ->add('email',EmailType::class,array('label'=>'Courriel du nouveau membre'))
            ->add('join_to',AutocompleteBeneficiaryType::class,array('label'=>'Email ou nom du compte parent','required'=>false))
            ->add('beneficiaries_emails',CollectionType::class,array('label'=>'Email beneficiaire',
                'required'=>false,
                'entry_type' => EmailType::class,
                'entry_options'  => array('label'=>'Email beneficiaire','attr'=>array('placehoder'=>'email@domain.fr'),'required'=>false,'constraints' => [new UniqueEmail()]),
                'allow_add'=>true))
            ->add('amount', TextType::class, array('label' => 'Montant','attr'=>array('placeholder'=>'15'),'required'=>false))
            ->add('mode', ChoiceType::class, array('choices'  => array(
                'Espèce' => Registration::TYPE_CASH,
                'Chèque' => Registration::TYPE_CHECK,
                'Cairn' => Registration::TYPE_LOCAL,
//                'Helloasso' => Registration::TYPE_HELLOASSO,
            ),'label' => 'Mode de réglement','placeholder' => '','required' => false)); //todo, make it dynamic

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

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_anonymous_beneficiary';
    }


}
