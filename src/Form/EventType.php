<?php

namespace App\Form;

use App\Entity\Event;
use App\Repository\EventKindRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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

            $form->add('title', TextType::class, ['label' => 'Titre'])
                ->add('kind', EntityType::class, [
                    'label' => 'Type d\'événement',
                    'class' => 'App:EventKind',
                    'choice_label' => 'name',
                    'multiple' => false,
                    'required' => false,
                    'query_builder' => function (EventKindRepository $repository) {
                        return $repository->createQueryBuilder('ek')
                            ->orderBy('ek.name', 'ASC')
                        ;
                    },
                ])
                ->add('date', DateTimeType::class, [
                    'required' => true,
                    'input'  => 'datetime',
                    'date_widget' => 'single_text',
                    'time_widget' => 'single_text',
                    'label' => 'Date & heure de début',
                    'attr' => [
                        'class' => 'datepicker',
                    ],
                ])
                ->add('end', DateTimeType::class, [
                    'required' => false,
                    'input'  => 'datetime',
                    'date_widget' => 'single_text',
                    'time_widget' => 'single_text',
                    'label' => 'Date & heure de fin (optionnel)',
                    'attr' => [
                        'class' => 'datepicker',
                    ],
                ])
                ->add('location', TextType::class, ['label' => 'Lieu', 'required' => false])
                ->add('description', MarkdownEditorType::class, ['label' => 'Description', 'required' => false])
                ->add('imgFile', VichImageType::class, [
                    'required' => false,
                    'allow_delete' => true,
                    'download_link' => true,
                ])
                ->add('displayed_home', CheckboxType::class, [
                    'required' => false,
                    'label' => 'Mettre en avant',
                    'attr' => ['class' => 'filled-in'],
                ])
            ;

            if ($userData && $userData->getId()) {
                $form->add('need_proxy', CheckboxType::class, [
                    'required' => false,
                    'label' => 'Utilise des procurations (AG, ...)',
                    'attr' => ['class' => 'filled-in']]);
                $form->add('anonymous_proxy', CheckboxType::class, [
                    'required' => false,
                    'label' => 'Autoriser les procurations anonymes',
                    'attr' => ['class' => 'filled-in']]);
                $form->add('max_date_of_last_registration', DateType::class, [
                    'required' => false,
                    'input' => 'datetime',
                    'widget' => 'single_text',
                    'label' => 'Date maximale d\'adhésion pour voter',
                    'attr' => ['class' => 'datepicker'],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'App_event';
    }
}
