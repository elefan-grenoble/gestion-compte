<?php

namespace App\Form;

use App\Entity\Beneficiary;
use App\Entity\Task;
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

class TaskType extends AbstractType
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
            $taskData = $event->getData();

            if ($user->hasRole('ROLE_ADMIN')||$user->hasRole('ROLE_SUPER_ADMIN')){
                $form->add('commissions',EntityType::class, array(
                    'class' => 'App:Commission',
                    'choice_label'     => 'name',
                    'multiple'     => true,
                    'required' => true,
                    'label'=>'Commission(s)'
                ));
            }else{
                $form->add('commissions',EntityType::class, array(
                    'class' => 'App:Commission',
                    'choices' => $user->getBeneficiary()->getCommissions(),
                    'choice_label'     => 'name',
                    'multiple'     => true,
                    'required' => true,
                    'label'=>'Commission(s) / College(s)'
                ));
            }

            $form->add('title',TextType::class,array('label'=>'titre'))
                ->add('due_date',TextType::class,array('required' => true,'attr'=>array('class'=>'datepicker'),'label'=>'Echéance'))
                ->add('priority',ChoiceType::class,array(
                    'label' => 'priorité',
                    'choices' => array(
                        "Non definie" => 0,
                        Task::PRIORITY_ANNEXE_VALUE." ANNEXE" => Task::PRIORITY_ANNEXE_VALUE,
                        Task::PRIORITY_NORMAL_VALUE." NORMAL" => Task::PRIORITY_NORMAL_VALUE,
                        Task::PRIORITY_IMPORTANT_VALUE." IMPORTANT" => Task::PRIORITY_IMPORTANT_VALUE,
                        Task::PRIORITY_URGENT_VALUE." URGENT" => Task::PRIORITY_URGENT_VALUE,
                    )
                ));

            if ($taskData && $taskData->getId()){
                $form->add('created_at',TextType::class,array('required' => true,'attr'=>array('class'=>'datepicker'),'label'=>'Début'));
                $form->add('closed', CheckboxType::class,array('required' => false,'label'=>'Terminée'));
                $form->add('status', TextType::class,array('required' => false,'label'=>'Status'));
                if ($taskData->getCommissions()->count() > 0){
                    $collection = array();
                    foreach ($taskData->getCommissions() as $commission){
                        $collection = array_merge($commission->getBeneficiaries()->toArray(),$collection);
                    }
                    $beneficiaries = new ArrayCollection($collection);
                    $form->add('owners',EntityType::class, array(
                        'class' => Beneficiary::class,
                        'label' => 'Personne(s) ressource(s)',
                        'choice_label'     => 'display_name',
                        'choices' => $beneficiaries,
                        'multiple'     => true,
                        'required' => false
                    ));
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
            'data_class' => Task::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_task';
    }


}
