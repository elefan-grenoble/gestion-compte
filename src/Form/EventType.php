<?php

namespace App\Form;

use App\Entity\Beneficiary;
use App\Entity\Event;
use App\Entity\Task;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;


class EventType extends AbstractType
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
            $userData = $event->getData();

            $form->add('title',TextType::class,array('label'=>'titre'))
                ->add('date',DateTimeType::class,array('required' => true,
                    'input'  => 'datetime',
                    'date_widget' => 'single_text',
                    'time_widget' => 'single_text',
                    'label' => 'Date & heure',
                    'attr' => [
                        'class' => 'datepicker'
                    ]
                ))
                ->add('description',MarkdownEditorType::class,array('label'=>'Description','required' => false));
            $form->add('imgFile', VichImageType::class, array(
                'required' => false,
                'allow_delete' => true,
                'download_link' => true,
            ));

            if ($userData && $userData->getId()){
                $form->add('need_proxy', CheckboxType::class,array('required' => false,'label'=>'Utilise des procurations (AG, ...)'));
                $form->add('min_date_of_last_registration', DateType::class,array('required' => false,
                    'input' => 'datetime',
                    'widget' => 'single_text',
                    'label' => 'Date minimale d\'adhésion pour voter',
                    'attr' => [
                        'class' => 'datepicker'
                    ]
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
            'data_class' => Event::class
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_event';
    }


}
