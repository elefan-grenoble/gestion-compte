<?php

namespace AppBundle\Form;

use AppBundle\Form\AutocompleteBeneficiaryType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

class MembershipShiftExemptionType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('shiftExemption', null, ['label' => 'Nature de l\'exemption'])
                ->add('description', TextareaType::class, ['label' => 'Commentaire', 'attr' => ['class' => 'materialize-textarea']])
                ->add('start', DateType::class, ['html5' => false, 'widget' => 'single_text', 'label' => 'Début (premier jour du cycle)', 'attr' => ['class' => 'datepicker']])
                ->add('end', DateType::class, ['html5' => false, 'widget' => 'single_text', 'label' => 'Fin (dernier jour du cycle)', 'attr' => ['class' => 'datepicker'],
                    'constraints' => [
                        new GreaterThan([
                            'propertyPath' => 'parent.all[start].data'
                        ])]]);

        if (!$options['edit']) {
            $builder->add('beneficiary', AutocompleteBeneficiaryType::class, [
                'mapped' => false,
                'label' => "Bénéficiaire",
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\MembershipShiftExemption',
            'edit' => false,
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
