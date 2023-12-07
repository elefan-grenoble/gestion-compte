<?php
//DependencyInjection/SearchUserFormHelper.php
namespace AppBundle\Service;

use AppBundle\Repository\CommissionRepository;
use AppBundle\Repository\FormationRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SearchUserFormHelper
{
    private $container;
    private $use_fly_and_fixed;
    private $fly_and_fixed_entity_flying;
    private $maximum_nb_of_beneficiaries_in_membership;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->use_fly_and_fixed = $this->container->getParameter('use_fly_and_fixed');
        $this->fly_and_fixed_entity_flying = $this->container->getParameter('fly_and_fixed_entity_flying');
        $this->maximum_nb_of_beneficiaries_in_membership = $this->container->getParameter('maximum_nb_of_beneficiaries_in_membership');
    }

    public function getSearchForm($formBuilder, $type = null, $disabledFields = []) {
        $formBuilder->add('withdrawn', ChoiceType::class, [
            'label' => $this->container->getParameter('member_withdrawn_icon') . ' fermé',
            'required' => false,
            'disabled' => in_array('withdrawn', $disabledFields) ? true : false,
            'choices' => [
                'fermé' => 2,
                'ouvert' => 1,
            ],
            'data' => 1
        ])
        ->add('enabled', ChoiceType::class, [
            'label' => $this->container->getParameter('user_account_enabled_icon') . ' activé',
            'required' => false,
            'choices' => [
                'activé' => 2,
                'Non activé' => 1,
            ]
        ])
        ->add('frozen', ChoiceType::class, [
            'label' => $this->container->getParameter('member_frozen_icon') . ' gelé',
            'required' => false,
            'choices' => [
                'gelé' => 2,
                'Non gelé' => 1,
            ]
        ]);
        if (!$type) {
            $formBuilder->add('exempted', ChoiceType::class, [
                'label' => $this->container->getParameter('member_exempted_icon') . ' exempté',
                'required' => false,
                'choices' => [
                    'exempté' => 2,
                    'Non exempté' => 1,
                ]
            ]);
            if ($this->maximum_nb_of_beneficiaries_in_membership > 1) {
                $formBuilder->add('beneficiary_count', ChoiceType::class, [
                    'label' => 'nb de bénéficiaires',
                    'required' => false,
                    'choices' => [  // TODO: make dynamic depending on maximum_nb_of_beneficiaries_in_membership
                        '1' => 2,
                        '2' => 3,
                    ]
                ]);
            }
            $formBuilder->add('has_first_shift_date', ChoiceType::class, [
                'label' => 'créneau inscrit',
                'required' => false,
                'choices' => [
                    'Oui' => 2,
                    'Non (jamais inscrit à un créneau)' => 1,
                ]
            ]);
        }
        $formBuilder->add('membernumber', TextType::class, [
            'label' => '# =',
            'required' => false,
        ])
        ->add('membernumbergt', IntegerType::class, [
            'label' => '# >',
            'required' => false
        ])
        ->add('membernumberlt', IntegerType::class, [
            'label' => '# <',
            'required' => false
        ]);
        if (!$type) {
            $formBuilder->add('membernumberdiff', TextType::class, [
                'label' => '# <>',
                'required' => false
            ]);
        }
        $formBuilder->add('registration', ChoiceType::class, [
            'label' => $this->container->getParameter('member_registration_missing_icon') . ' adhéré',
            'required' => false,
            'disabled' => in_array('registration', $disabledFields) ? true : false,
            'choices' => [
                'adhéré' => 2,
                'Non adhéré' => 1,
            ]
        ]);
        if (!$type) {
            $formBuilder->add('registrationdate', TextType::class, [
                'label' => 'le',
                'required' => false,
                'attr' => [
                    'class' => 'datepicker'
                ]
            ])
            ->add('registrationdategt', TextType::class, [
                'label' => 'après le',
                'required' => false,
                'attr' => [
                    'class' => 'datepicker'
                ]
            ])
            ->add('registrationdatelt', TextType::class, [
                'label' => 'avant le',
                'required' => false,
                'attr' => [
                    'class' => 'datepicker'
                ]
            ])
            ->add('lastregistrationdate', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'label' => 'le',
                'required' => false,
                'attr' => [
                    'class' => 'datepicker'
                ]
            ]);
        }
        $formBuilder->add('lastregistrationdatelt', DateType::class, [
            'widget' => 'single_text',
            'html5' => false,
            'label' => 'avant le',
            'required' => false,
            'disabled' => in_array('lastregistrationdatelt', $disabledFields) ? true : false,
            'attr' => [
                'class' => 'datepicker',
            ]
        ])
        ->add('lastregistrationdategt', DateType::class, [
            'widget' => 'single_text',
            'html5' => false,
            'label' => 'après le',
            'required' => false,
            'disabled' => in_array('lastregistrationdategt', $disabledFields) ? true : false,
            'attr' => [
                'class' => 'datepicker'
            ]
        ]);
        if (!$type) {
            $formBuilder->add('username', TextType::class, [
                'label' => 'username',
                'required' => false
            ]);
        }
        $formBuilder->add('compteurlt', NumberType::class, [
            'label' => 'max',
            'required' => false,
            'disabled' => in_array('compteurlt', $disabledFields) ? true : false,
        ])
        ->add('compteurgt', NumberType::class, [
            'label' => 'min',
            'required' => false
        ])
        ->add('firstname', TextType::class, [
            'label' => 'prénom',
            'required' => false
        ])
        ->add('lastname', TextType::class, [
            'label' => 'nom',
            'required' => false
        ])
        ->add('email', TextType::class, [
            'label' => 'email',
            'required' => false
        ]);
        if (!$type) {
            $formBuilder->add('phone', ChoiceType::class, [
                'label' => 'téléphone renseigné',
                'required' => false,
                'choices' => [
                    'Oui' => 2,
                    'Non (pas renseigné)' => 1,
                ]
            ]);
        }
        if ($this->use_fly_and_fixed) {
            $formBuilder->add('flying', ChoiceType::class, [
                'label' => (($this->fly_and_fixed_entity_flying == 'Membership') ? $this->container->getParameter('member_flying_icon') : $this->container->getParameter('beneficiary_flying_icon')) . ' volant',
                'required' => false,
                'disabled' => in_array('flying', $disabledFields) ? true : false,
                'choices' => [
                    'Oui' => 2,
                    'Non (fixe)' => 1,
                ],
            ]);
            $formBuilder->add('has_period_position', ChoiceType::class, [
                'label' => 'créneau fixe',
                'required' => false,
                'disabled' => in_array('has_period_position', $disabledFields) ? true : false,
                'choices' => [
                    'Oui' => 2,
                    'Non (pas de créneau fixe)' => 1,
                ]
            ]);
        }
        if (!$type) {
            $formBuilder->add('formations', EntityType::class, [
                'class' => 'AppBundle:Formation',
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'label' =>'Avec le(s) formations(s)',
                'query_builder' => function(FormationRepository $repository) {
                    $qb = $repository->createQueryBuilder('f');
                    return $qb->orderBy('f.name', 'ASC');
                }
            ])
            ->add('or_and_exp_formations', ChoiceType::class, [
                'label' => 'Toutes ?',
                'required' => true,
                'choices' => [
                    '' => 0,
                    'Toutes ces formations' => 1,
                ]
            ])
            ->add('commissions', EntityType::class, [
                'class' => 'AppBundle:Commission',
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'label'=>'Dans la/les commissions(s)',
                'query_builder' => function(CommissionRepository $repository) {
                    $qb = $repository->createQueryBuilder('c');
                    return $qb->orderBy('c.name', 'ASC');
                }
            ])
            ->add('not_formations', EntityType::class, [
                'class' => 'AppBundle:Formation',
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'label'=>'Sans le(s) formations(s)',
                'query_builder' => function(FormationRepository $repository) {
                    $qb = $repository->createQueryBuilder('f');
                    return $qb->orderBy('f.name', 'ASC');
                }
            ])
            ->add('not_commissions', EntityType::class, [
                'class' => 'AppBundle:Commission',
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'label'=>'Hors de la/les commissions(s)',
                'query_builder' => function(CommissionRepository $repository) {
                    $qb = $repository->createQueryBuilder('c');
                    return $qb->orderBy('c.name', 'ASC');
                }
            ]);
        }
        $formBuilder->add('action', HiddenType::class, [
        ])
        ->add('page', HiddenType::class, [
            'data' => '1'
        ])
        ->add('dir', HiddenType::class, [
        ])
        ->add('sort', HiddenType::class, [
        ])
        ->add('submit', SubmitType::class, [
            'label' => 'Filtrer',
            'attr' => [
                'class' => 'btn',
                'value' => 'show'
            ]
        ]);
        if (!$type) {
            $formBuilder->add('csv', SubmitType::class, [
                'label' => 'Export CSV',
                'attr' => [
                    'class' => 'btn',
                    'value' => 'csv'
                ]
            ])
            ->add('mail', SubmitType::class, [
                'label' => 'Envoyer un mail',
                'attr' => [
                    'class' => 'btn',
                    'value' => 'mail'
            ]]);
        }
        return $formBuilder->getForm();
    }

    public function createMemberFilterForm($formBuilder, $defaults) {
        $form = $this->getSearchForm($formBuilder);
        foreach ($defaults as $k => $v) {
            $form->get($k)->setData($v);
        }
        return $form;
    }

    public function createMemberNoRegistrationFilterForm($formBuilder, $defaults, $disabledFields = []) {
        $form = $this->getSearchForm($formBuilder, 'membership', $disabledFields);
        foreach ($defaults as $k => $v) {
            $form->get($k)->setData($v);
        }
        return $form;
    }

    public function createMemberLateRegistrationFilterForm($formBuilder, $defaults, $disabledFields = []) {
        $form = $this->getSearchForm($formBuilder, 'membership', $disabledFields);
        // set lastregistrationdatelt default
        $options = $form->get('lastregistrationdatelt')->getConfig()->getOptions();
        $options['required'] = true;
        $options['constraints'] = [
            new NotBlank(),
            new LessThanOrEqual($defaults['lastregistrationdatelt'])
        ];
        $form->add('lastregistrationdatelt', DateType::class, $options);
        foreach ($defaults as $k => $v) {
            $form->get($k)->setData($v);
        }
        return $form;
    }

    public function createMemberShiftTimeLogFilterForm($formBuilder, $defaults = [], $disabledFields = []) {
        $form = $this->getSearchForm($formBuilder, 'shifttimelog', $disabledFields);
        // set compteurlt default
        $options = $form->get('compteurlt')->getConfig()->getOptions();
        $options['required'] = true;
        $options['constraints'] = [
            new NotBlank(),
            new LessThanOrEqual($defaults['compteurlt'])
        ];
        $form->add('compteurlt', NumberType::class, $options);
        foreach ($defaults as $k => $v) {
            $form->get($k)->setData($v);
        }
        return $form;
    }

    public function createBeneficiaryFixeWithoutPeriodPositionForm($formBuilder, $defaults = [], $disabledFields = []) {
        $form = $this->getSearchForm($formBuilder, 'fixe_without_periodposition', $disabledFields);
        foreach ($defaults as $k => $v) {
            $form->get($k)->setData($v);
        }
        return $form;
    }

    /**
     * @param EntityManager $doctrineManager
     * @return QueryBuilder
     */
    public function initSearchQuery($doctrineManager, $type = null) {
        $qb = $doctrineManager->getRepository("AppBundle:Membership")->createQueryBuilder('m');
        $qb = $qb->leftJoin("m.beneficiaries", "b")->addSelect("b")
            ->leftJoin("b.user", "u")->addSelect("u")
            ->leftJoin("m.registrations", "r")->addSelect("r")
            ->leftJoin("r.helloassoPayment", "rhp")->addSelect("rhp")
            ->leftJoin("m.membershipShiftExemptions", "mse")->addSelect("mse")
            ->leftJoin("b.commissions", "c")->addSelect("c")
            ->leftJoin("b.formations", "f")->addSelect("f");

        if (in_array($type, ['noregistration', 'lateregistration', 'shifttimelog', 'fixe_without_periodposition'])) {
            $qb = $qb->leftJoin("m.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
                ->where('lr.id IS NULL') // registration is the last one registered
                ->addSelect("(SELECT SUM(ti.time) FROM AppBundle\Entity\TimeLog ti WHERE ti.type != 20 AND ti.membership = m.id) AS HIDDEN time")
                ->leftJoin("m.timeLogs", "tl")->addSelect("tl")
                ->leftJoin("m.notes", "n")->addSelect("n");
        }

        // do not include admin user
        $qb = $qb->andWhere('m.member_number > 0');

        return $qb;
    }

    /**
     * @param FormBuilder $form
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function processSearchFormAmbassadorData($form, &$qb) {
        if ($form->get('withdrawn')->getData() > 0) {
            $qb = $qb->andWhere('m.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $form->get('withdrawn')->getData()-1);
        }
        if ($form->get('enabled')->getData() > 0) {
            $qb = $qb->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', $form->get('enabled')->getData()-1);
        }
        if ($form->get('frozen')->getData() > 0) {
            $qb = $qb->andWhere('m.frozen = :frozen')
                ->setParameter('frozen', $form->get('frozen')->getData()-1);
        }
        if (!is_null($form->get('compteurlt')->getData())) {
            $compteurlt = $form->get('compteurlt')->getData();
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t1.membership) FROM AppBundle\Entity\TimeLog t1 WHERE t1.type != 20 GROUP BY t1.membership HAVING SUM(t1.time) < :compteurlt * 60)')
                ->setParameter('compteurlt', $compteurlt);
        }
        if (!is_null($form->get('compteurgt')->getData())) {
            $compteurgt = $form->get('compteurgt')->getData();
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t2.membership) FROM AppBundle\Entity\TimeLog t2 WHERE t2.type != 20 GROUP BY t2.membership HAVING SUM(t2.time) > :compteurgt * 60)')
                ->setParameter('compteurgt', $compteurgt);
        }
        if ($form->get('registration')->getData()) {
            if ($form->get('registration')->getData() == 2) {
                $qb = $qb->andWhere('r.date IS NOT NULL');
            } else if ($form->get('registration')->getData() == 1) {
                $qb = $qb->andWhere('r.date IS NULL');
            }
        }
        if ($form->get('lastregistrationdategt')->getData()) {
            $date = $form->get('lastregistrationdategt')->getData();
            $qb = $qb->andWhere('r.date > :lastregistrationdategt')
                ->setParameter('lastregistrationdategt', $date);
        }
        if ($form->get('lastregistrationdatelt')->getData()) {
            $date = $form->get('lastregistrationdatelt')->getData();
            $qb = $qb->andWhere('r.date < :lastregistrationdatelt')
                ->setParameter('lastregistrationdatelt', $date);
        }
        if ($form->get('membernumber')->getData()) {
            $qb = $qb->andWhere('m.member_number = :membernumber')
                     ->setParameter('membernumber', $form->get('membernumber')->getData());
        }
        if ($form->get('membernumbergt')->getData()) {
            $qb = $qb->andWhere('m.member_number > :membernumbergt')
                     ->setParameter('membernumbergt', $form->get('membernumbergt')->getData());
        }
        if ($form->get('membernumberlt')->getData()) {
            $qb = $qb->andWhere('m.member_number < :membernumberlt')
                ->setParameter('membernumberlt', $form->get('membernumberlt')->getData());
        }
        if ($form->get('firstname')->getData()) {
            $qb = $qb->andWhere('b.firstname LIKE :firstname')
                ->setParameter('firstname', '%'.$form->get('firstname')->getData().'%');
        }
        if ($form->get('lastname')->getData()) {
            $qb = $qb->andWhere('b.lastname LIKE :lastname')
                ->setParameter('lastname', '%'.$form->get('lastname')->getData().'%');
        }
        if ($form->get('email')->getData()) {
            $list  = explode(', ', $form->get('email')->getData());
            if (count($list)>1) {
                $qb = $qb->andWhere('u.email IN (:emails)')
                    ->setParameter('emails', $list);
            } else {
                $qb = $qb->andWhere('u.email LIKE :email')
                    ->setParameter('email', '%'.$form->get('email')->getData().'%');
            }
        }

        if ($this->use_fly_and_fixed) {
            if ($form->get('flying')->getData() > 0) {
                if ($this->fly_and_fixed_entity_flying == 'Membership') {
                    $qb = $qb->andWhere('m.flying = :flying')
                        ->setParameter('flying', $form->get('flying')->getData()-1);
                } else {
                    $qb = $qb->andWhere('b.flying = :flying')
                        ->setParameter('flying', $form->get('flying')->getData()-1);
                }
            }
            if ($form->get('has_period_position')->getData() > 0) {
                $qb = $qb->leftJoin("b.periodPositions", "pp")->addSelect("pp");
                if ($form->get('has_period_position')->getData() == 2) {
                    $qb = $qb->andWhere('pp.id IS NOT NULL');
                } else if ($form->get('has_period_position')->getData() == 1) {
                    $qb = $qb->andWhere('pp.id IS NULL');
                }
            }
        }

        return $qb;
    }

    /**
     * @param FormBuilder $form
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function processSearchFormData($form, &$qb) {
        $now = new \DateTime('now');

        if ($form->get('withdrawn')->getData() > 0) {
            $qb = $qb->andWhere('m.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $form->get('withdrawn')->getData()-1);
        }
        if ($form->get('enabled')->getData() > 0) {
            $qb = $qb->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', $form->get('enabled')->getData()-1);
        }
        if ($form->get('frozen')->getData() > 0) {
            $qb = $qb->andWhere('m.frozen = :frozen')
                ->setParameter('frozen', $form->get('frozen')->getData()-1);
        }
        if ($form->get('exempted')->getData() > 0) {
            if ($form->get('exempted')->getData() == 2) {
                $qb = $qb->andWhere('mse.start <= :date AND mse.end >= :date')
                    ->setParameter('date', $now);
            } else if ($form->get('exempted')->getData() == 1) {
                $qb = $qb->andWhere('mse.start IS NULL OR mse.start > :date OR mse.end < :date')
                    ->setParameter('date', $now);
            }
        }
        if ($this->maximum_nb_of_beneficiaries_in_membership > 1) {
            if ($form->get('beneficiary_count')->getData() > 0) {
                $qb = $qb->andWhere('SIZE(m.beneficiaries) = :beneficiary_count')
                    ->setParameter('beneficiary_count', $form->get('beneficiary_count')->getData()-1);
            }
        }
        if ($form->get('has_first_shift_date')->getData() > 0) {
            if ($form->get('has_first_shift_date')->getData() == 2) {
                $qb = $qb->andWhere('m.firstShiftDate IS NOT NULL');
            } else if ($form->get('has_first_shift_date')->getData() == 1) {
                $qb = $qb->andWhere('m.firstShiftDate IS NULL');
            }
        }

        if ($form->get('registration')->getData()) {
            if ($form->get('registration')->getData() == 2) {
                $qb = $qb->andWhere('r.date IS NOT NULL');
            } else if ($form->get('registration')->getData() == 1) {
                $qb = $qb->andWhere('r.date IS NULL');
            }
        }
        if ($form->get('registrationdate')->getData()) {
            $qb = $qb->andWhere('r.date LIKE :registrationdate')
                ->setParameter('registrationdate', $form->get('registrationdate')->getData().'%');
        }
        if ($form->get('registrationdategt')->getData()) {
            $qb = $qb->andWhere('r.date > :registrationdategt')
                ->setParameter('registrationdategt', $form->get('registrationdategt')->getData());
        }
        if ($form->get('registrationdatelt')->getData()) {
            $qb = $qb->andWhere('r.date < :registrationdatelt')
                ->setParameter('registrationdatelt', $form->get('registrationdatelt')->getData());
        }

        if ($form->get('lastregistrationdate')->getData() || $form->get('lastregistrationdategt')->getData() || $form->get('lastregistrationdatelt')->getData()) {
            $qb = $qb
                ->leftJoin("m.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
                ->andWhere('lr.id IS NULL');
            if ($form->get('lastregistrationdate')->getData()) {
                $qb = $qb
                    ->andWhere('r.date LIKE :lastregistrationdate')
                    ->setParameter('lastregistrationdate', $form->get('lastregistrationdate')->getData()->format('Y-m-d').'%');
            }
            if ($form->get('lastregistrationdategt')->getData()) {
                $qb = $qb
                    ->andWhere('r.date > :lastregistrationdategt')
                    ->setParameter('lastregistrationdategt', $form->get('lastregistrationdategt')->getData());
            }
            if ($form->get('lastregistrationdatelt')->getData()) {
                $qb = $qb
                    ->andWhere('r.date < :lastregistrationdatelt')
                    ->setParameter('lastregistrationdatelt', $form->get('lastregistrationdatelt')->getData());
            }
        }

        if (!is_null($form->get('compteurlt')->getData())) {
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t1.membership) FROM AppBundle\Entity\TimeLog t1 WHERE t1.type != 20 GROUP BY t1.membership HAVING SUM(t1.time) < :compteurlt * 60)')
                ->setParameter('compteurlt', $form->get('compteurlt')->getData());
        }
        if (!is_null($form->get('compteurgt')->getData())) {
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t2.membership) FROM AppBundle\Entity\TimeLog t2 WHERE t2.type != 20 GROUP BY t2.membership HAVING SUM(t2.time) > :compteurgt * 60)')
                ->setParameter('compteurgt', $form->get('compteurgt')->getData());
        }

        if ($form->get('membernumber')->getData()) {
            $list  = explode(', ', $form->get('membernumber')->getData());
            if (count($list)>1) {
                $qb = $qb->andWhere('m.member_number IN (:membernumber)')
                    ->setParameter('membernumber', $list);
            } else {
                $qb = $qb->andWhere('m.member_number = :membernumber')
                    ->setParameter('membernumber', $form->get('membernumber')->getData());
            }
        }
        if ($form->get('membernumbergt')->getData()) {
            $qb = $qb->andWhere('m.member_number > :membernumbergt')
                ->setParameter('membernumbergt', $form->get('membernumbergt')->getData());
        }
        if ($form->get('membernumberlt')->getData()) {
            $qb = $qb->andWhere('m.member_number < :membernumberlt')
                ->setParameter('membernumberlt', $form->get('membernumberlt')->getData());
        }
        if ($form->get('membernumberdiff')->getData()) {
            $list  = explode(', ', $form->get('membernumberdiff')->getData());
            if (count($list)>1) {
                $qb = $qb->andWhere('m.member_number NOT IN (:membernumberdiff)')
                    ->setParameter('membernumberdiff', $list);
            } else {
                $qb = $qb->andWhere('m.member_number != :membernumberdiff')
                    ->setParameter('membernumberdiff', $form->get('membernumberdiff')->getData());
            }
        }
        if ($form->get('username')->getData()) {
            $list  = explode(', ', $form->get('username')->getData());
            if (count($list)>1) {
                $qb = $qb->andWhere('u.username IN (:usernames)')
                    ->setParameter('usernames', $list);
            } else {
                $qb = $qb->andWhere('u.username LIKE :username')
                    ->setParameter('username', '%'.$form->get('username')->getData().'%');
            }
        }
        if ($form->get('firstname')->getData()) {
            $qb = $qb->andWhere('b.firstname LIKE :firstname')
                ->setParameter('firstname', '%'.$form->get('firstname')->getData().'%');
        }
        if ($form->get('lastname')->getData()) {
            $qb = $qb->andWhere('b.lastname LIKE :lastname')
                ->setParameter('lastname', '%'.$form->get('lastname')->getData().'%');
        }
        if ($form->get('email')->getData()) {
            $list  = explode(', ', $form->get('email')->getData());
            if (count($list)>1) {
                $qb = $qb->andWhere('u.email IN (:emails)')
                    ->setParameter('emails', $list);
            } else {
                $qb = $qb->andWhere('u.email LIKE :email')
                    ->setParameter('email', '%'.$form->get('email')->getData().'%');
            }
        }
        if ($form->get('phone')->getData() > 0) {
            if ($form->get('phone')->getData() == 1) { // non renseigné
                $qb = $qb->andWhere('b.phone < 100000000');
            } else {
                $qb = $qb->andWhere('b.phone > 100000000');
            }
        }

        if ($this->use_fly_and_fixed) {
            if ($form->get('flying')->getData() > 0) {
                if ($this->fly_and_fixed_entity_flying == 'Membership') {
                    $qb = $qb->andWhere('m.flying = :flying')
                        ->setParameter('flying', $form->get('flying')->getData()-1);
                } else {
                    $qb = $qb->andWhere('b.flying = :flying')
                        ->setParameter('flying', $form->get('flying')->getData()-1);
                }
            }
            if ($form->get('has_period_position')->getData() > 0) {
                $qb = $qb->leftJoin("b.periodPositions", "pp")->addSelect("pp");
                if ($form->get('has_period_position')->getData() == 2) {
                    $qb = $qb->andWhere('pp.id IS NOT NULL');
                } else if ($form->get('has_period_position')->getData() == 1) {
                    $qb = $qb->andWhere('pp.id IS NULL');
                }
            }
        }

        $join_formations = false;
        if ($form->get('formations')->getData() && count($form->get('formations')->getData())) {
            if (($form->get('or_and_exp_formations')->getData() > 0) && (count($form->get('formations')->getData()) > 1)) { // AND not OR
                $formations = $form->get('formations')->getData();
                $ids_groups = array();
                foreach ($formations as $formation) {
                    $tmp_qb = clone $qb;
                    $tmp_qb = $tmp_qb->andWhere('f.id IN (:fid)')
                        ->setParameter('fid', $formation);
                    $ids_groups[] = $tmp_qb->select('DISTINCT m.id')->getQuery()->getArrayResult();
                }
                $ids = $ids_groups[0];
                for( $i= 1; $i < count($ids_groups); $i++) {
                    foreach ($ids_groups[$i] as $j => $array) {
                        if (!in_array($array, $ids)) {
                            unset($ids_groups[$i][$j]);
                        }
                    }
                    $ids = $ids_groups[$i];
                }
                $qb = $qb->andWhere('m.id IN (:all_formations)')
                    ->setParameter('all_formations', $ids);
            } else {
                $qb = $qb->andWhere('f.id IN (:fids)')
                    ->setParameter('fids', $form->get('formations')->getData());
                $join_formations = true;
            }
        }
        $join_commissions = false;
        if ($form->get('commissions')->getData() && count($form->get('commissions')->getData())) {
            $qb->andWhere('c.id IN (:cids)')
                ->setParameter('cids', $form->get('commissions')->getData());
            $join_commissions = true;
        }
        if ($form->get('not_formations')->getData() && count($form->get('not_formations')->getData())) {
            $nrqb = clone $qb;
            if (!$join_formations) {
                $nrqb = $nrqb->andWhere('f.id IN (:fids)');
            }
            $nrqb->setParameter('fids', $form->get('not_formations')->getData() );
            $subQuery = $nrqb->select('DISTINCT m.id')->getQuery()->getArrayResult();

            if (count($subQuery)) {
                $qb = $qb->andWhere('m.id NOT IN (:subQueryformations)')
                    ->setParameter('subQueryformations', $subQuery);
            }

        }
        if ($form->get('not_commissions')->getData() && count($form->get('not_commissions')->getData())) {
            $ncqb = clone $qb;
            if (!$join_commissions) {
                $ncqb->andWhere('c.id IN (:cids)');
            }
            $ncqb->setParameter('cids', $form->get('not_commissions')->getData());
            $subQuery = $ncqb->select('DISTINCT m.id')->getQuery()->getArrayResult();

            if (count($subQuery)) {
                $qb = $qb->andWhere('m.id NOT IN (:subQueryformations)')
                    ->setParameter('subQueryformations', $subQuery);
            }
        }

        return $qb;
    }
}
