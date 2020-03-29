<?php

namespace App\Form;

use App\Entity\Beneficiary;
use App\Entity\Commission;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;


class CommissionType extends AbstractType
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
        if ($user->hasRole('ROLE_ADMIN')||$user->hasRole('ROLE_SUPER_ADMIN')) {
            $builder
                ->add('name', TextType::class, array('constraints' => array(new NotBlank()), 'label' => 'Nom'));
        }
        $builder
            ->add('description',MarkdownEditorType::class,array('label'=>'Description'))
            ->add('email',EmailType::class,array('constraints' => array( new NotBlank(), new Email()),'label'=>'Courriel'));

        $builder->add('next_meeting_date',DateTimeType::class,array('required' => false,
            'input'  => 'datetime',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
            'label' => 'Date & heure de la prochaine réunion',
            'attr' => [
                'class' => 'datepicker'
            ]
        ));
        $builder->add('next_meeting_desc', TextType::class, array('required' => false, 'label' => 'Libelé de la prochaine réunion'));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user) {
            $form = $event->getForm();
            $commission = $event->getData();
            if ($user->hasRole('ROLE_ADMIN')||$user->hasRole('ROLE_SUPER_ADMIN')) {
                $form->add('owners', EntityType::class, array(
                    'class' => Beneficiary::class,
                    'choice_label' => 'display_name',
                    'choices' => $commission->getBeneficiaries(),
                    'multiple' => true,
                    'required' => false
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
            'data_class' => Commission::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_commission';
    }


}
