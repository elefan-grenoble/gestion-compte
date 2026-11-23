<?php

namespace App\Form;

use App\Entity\Formation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Dropdown to choose who a shift (or a typical week position) is reserved to:
 * a formation, or the new beneficiaries ("Nouveau ★").
 *
 * "Nouveau" is not a formation: it is a beneficiary status (see Beneficiary::isNew()).
 * The field is not mapped: it reads and writes both `formation` and `newBeneficiaryOnly`
 * on the entity of its parent form (Shift or PeriodPosition), so that the two can never be
 * set at the same time.
 */
class FormationOrNewType extends AbstractType
{
    public const NEW_CHOICE = 'new';

    private $em;
    private $newIcon;

    /** @var null|string[] */
    private $labels;

    public function __construct(EntityManagerInterface $em, string $newIcon)
    {
        $this->em = $em;
        $this->newIcon = $newIcon;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $entity = self::getReservableEntity($event->getForm());
            if (null === $entity) {
                return;
            }

            if ($entity->isNewBeneficiaryOnly()) {
                $event->setData(self::NEW_CHOICE);
            } elseif ($entity->getFormation()) {
                $event->setData((string) $entity->getFormation()->getId());
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $entity = self::getReservableEntity($form);
            // an invalid choice must not wipe what the entity currently holds
            if (null === $entity || !$form->isSynchronized()) {
                return;
            }

            $choice = $form->getData();
            $entity->setNewBeneficiaryOnly(self::NEW_CHOICE === $choice);
            $entity->setFormation(
                (null === $choice || '' === $choice || self::NEW_CHOICE === $choice)
                    ? null
                    : $this->em->find(Formation::class, (int) $choice)
            );
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label' => 'Formation',
            'mapped' => false,
            'required' => false,
            'choice_translation_domain' => false,
            // lazy: the formations are only loaded when the field is actually built
            'choices' => function (Options $options) {
                return array_map('strval', array_keys($this->getLabels()));
            },
            'choice_label' => function ($choice) {
                return $this->getLabels()[$choice] ?? $choice;
            },
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix()
    {
        return 'formation_or_new';
    }

    /**
     * Label of each choice, by choice value: "Nouveau ★" first, then the formations by name.
     *
     * @return string[]
     */
    private function getLabels(): array
    {
        if (null === $this->labels) {
            $this->labels = [self::NEW_CHOICE => 'Nouveau ' . $this->newIcon];
            foreach ($this->em->getRepository(Formation::class)->findBy([], ['name' => 'ASC']) as $formation) {
                $this->labels[(string) $formation->getId()] = $formation->getName();
            }
        }

        return $this->labels;
    }

    /**
     * The Shift or PeriodPosition the field belongs to, if any.
     *
     * @return null|\App\Entity\PeriodPosition|\App\Entity\Shift
     */
    private static function getReservableEntity(FormInterface $form)
    {
        $parent = $form->getParent();
        $entity = $parent ? $parent->getData() : null;

        return (is_object($entity) && method_exists($entity, 'isNewBeneficiaryOnly')) ? $entity : null;
    }
}
