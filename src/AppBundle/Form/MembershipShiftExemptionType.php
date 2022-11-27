<?php

namespace AppBundle\Form;

use AppBundle\Form\AutocompleteBeneficiaryType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use \Datetime;

class MembershipShiftExemptionType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('shiftExemption', null, ['label' => 'Nature de l\'exemption'])
                ->add('description', TextareaType::class, ['label' => 'Commentaire', 'attr' => ['class' => 'materialize-textarea']])
                ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                    $membershipShiftExemption = $event->getData();
                    $form = $event->getForm();

                    // checks if the MembershipShiftExamption object is "new"
                    if (!$membershipShiftExemption || null === $membershipShiftExemption->getId()) {
                        $form->add('beneficiary', AutocompleteBeneficiaryType::class, [
                            'mapped' => false,
                            'label' => "Bénéficiaire",
                        ]);
                    }
                    $now = new DateTime();
                    // checks if the MembershipShiftExamption object is "new" or start date in future
                    if (!$membershipShiftExemption || null === $membershipShiftExemption->getId() || ($membershipShiftExemption && $membershipShiftExemption->getStart() > $now)) {
                        $form->add('start', DateType::class, ['html5' => false, 'widget' => 'single_text', 'label' => 'Début (premier jour du cycle)', 'attr' => ['class' => 'datepicker'],
                            'constraints' => [
                                new GreaterThan([
                                    'value' => "today"
                                ])]]);
                    } else {
                        $form->add('start', DateType::class, ['html5' => false, 'widget' => 'single_text', 'label' => 'Début (premier jour du cycle)', 'disabled' => true]);
                    }
                    // checks if the MembershipShiftExamption object is "new" or end date in future
                    if (!$membershipShiftExemption || null === $membershipShiftExemption->getId() || $membershipShiftExemption->getEnd() > $now) {
                        $form->add('end', DateType::class, ['html5' => false, 'widget' => 'single_text', 'label' => 'Fin (dernier jour du cycle)', 'attr' => ['class' => 'datepicker'],
                            'constraints' => [
                                new GreaterThan([
                                    'propertyPath' => 'parent.all[start].data'
                                ])]]);
                    } else {
                        $form->add('end', DateType::class, ['html5' => false, 'widget' => 'single_text', 'label' => 'Fin (dernier jour du cycle)', 'disabled' => true]);
                    }
                });

    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\MembershipShiftExemption',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'appbundle_membershipshiftexemption';
    }


}
