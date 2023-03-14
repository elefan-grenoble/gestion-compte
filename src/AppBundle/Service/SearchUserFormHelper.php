<?php
//DependencyInjection/SearchUserFormHelper.php
namespace AppBundle\Service;

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

class SearchUserFormHelper {

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
        ]);
        if (!$type) {
            $formBuilder->add('enabled', ChoiceType::class, [
                'label' => $this->container->getParameter('user_account_enabled_icon') . ' activé',
                'required' => false,
                'choices' => [
                    'activé' => 2,
                    'Non activé' => 1,
                ]
            ]);
        }
        $formBuilder->add('frozen', ChoiceType::class, [
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
            $formBuilder->add('beneficiary_count', ChoiceType::class, [
                'label' => 'nb de bénéficiaires',
                'required' => false,
                'choices' => [  // TODO: make dynamic depending on maximum_nb_of_beneficiaries_in_membership
                    '1' => 2,
                    '2' => 3,
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
        $formBuilder->add('lastregistrationdategt', DateType::class, [
            'widget' => 'single_text',
            'html5' => false,
            'label' => 'après le',
            'required' => false,
            'attr' => [
                'class' => 'datepicker'
            ]
        ])
        ->add('lastregistrationdatelt', DateType::class, [
            'widget' => 'single_text',
            'html5' => false,
            'label' => 'avant le',
            'required' => false,
            'disabled' => in_array('lastregistrationdatelt', $disabledFields) ? true : false,
            'attr' => [
                'class' => 'datepicker',
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
                'label' => 'Téléphone renseigné ?',
                'required' => false,
                'choices' => [
                    'Renseigné' => 2,
                    'Non renseigné' => 1,
                ]
            ])
            ->add('formations', EntityType::class, [
                'class' => 'AppBundle:Formation',
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'label'=>'Avec le(s) formations(s)'
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
                'label'=>'Dans la/les commissions(s)'
            ])
            ->add('not_formations', EntityType::class, [
                'class' => 'AppBundle:Formation',
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'label'=>'Sans le(s) formations(s)'
            ])
            ->add('not_commissions', EntityType::class, [
                'class' => 'AppBundle:Commission',
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'label'=>'Hors de la/les commissions(s)'
            ])
            ->add('flying', ChoiceType::class, [
                'label' => $this->container->getParameter('beneficiary_flying_icon') . ' volant',
                'required' => false,
                'choices' => [
                    'Oui' => 2,
                    'Non (fixe)' => 1,
                ],
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

    public function createShiftTimeLogFilterForm($formBuilder, $defaults = [], $disabledFields = []) {
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

    public function createMembershipFilterForm($formBuilder, $defaults, $disabledFields = []) {
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

    /**
     * @param EntityManager $doctrineManager
     * @return QueryBuilder
     */
    public function initSearchQuery($doctrineManager) {
        $qb = $doctrineManager->getRepository("AppBundle:Membership")->createQueryBuilder('o');
        $qb = $qb->leftJoin("o.beneficiaries", "b")
            ->leftJoin("b.user", "u")
            ->leftJoin("o.registrations", "r")->addSelect("r")
            ->leftJoin("o.membershipShiftExemptions", "e");
        // do not include admin user
        $qb = $qb->andWhere('o.member_number > 0');
        return $qb;
    }

    /**
     * @param FormBuilder $form
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function processSearchFormAmbassadorData($form, &$qb) {
        if ($form->get('withdrawn')->getData() > 0) {
            $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                     ->setParameter('withdrawn', $form->get('withdrawn')->getData()-1);
        }
        if ($form->get('frozen')->getData() > 0) {
            $qb = $qb->andWhere('o.frozen = :frozen')
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
            $qb = $qb->andWhere('o.member_number = :membernumber')
                     ->setParameter('membernumber', $form->get('membernumber')->getData());
        }
        if ($form->get('membernumbergt')->getData()) {
            $qb = $qb->andWhere('o.member_number > :membernumbergt')
                     ->setParameter('membernumbergt', $form->get('membernumbergt')->getData());
        }
        if ($form->get('membernumberlt')->getData()) {
            $qb = $qb->andWhere('o.member_number < :membernumberlt')
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
        return $qb;
    }

    /**
     * @param FormBuilder $form
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function processSearchFormData($form,&$qb) {
        $now = new \DateTime('now');
        if ($form->get('withdrawn')->getData() > 0) {
            $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $form->get('withdrawn')->getData()-1);
        }
        if ($form->get('enabled')->getData() > 0) {
            $qb = $qb->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', $form->get('enabled')->getData()-1);
        }
        if ($form->get('frozen')->getData() > 0) {
            $qb = $qb->andWhere('o.frozen = :frozen')
                ->setParameter('frozen', $form->get('frozen')->getData()-1);
        }
        if ($form->get('exempted')->getData() > 0) {
            if ($form->get('exempted')->getData() == 2) {
                $qb = $qb->andWhere('e.start <= :date AND e.end >= :date')
                         ->setParameter('date', $now);
            } else if ($form->get('exempted')->getData() == 1) {
                $qb = $qb->andWhere('e.start IS NULL OR e.start > :date OR e.end < :date')
                         ->setParameter('date', $now);
            }
        }
        if ($form->get('beneficiary_count')->getData() > 0) {
            $qb = $qb->andWhere('SIZE(o.beneficiaries) = :beneficiary_count')
                ->setParameter('beneficiary_count', $form->get('beneficiary_count')->getData()-1);
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
                ->leftJoin("o.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
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
                $qb = $qb->andWhere('o.member_number IN (:membernumber)')
                    ->setParameter('membernumber', $list);
            } else {
                $qb = $qb->andWhere('o.member_number = :membernumber')
                    ->setParameter('membernumber', $form->get('membernumber')->getData());
            }
        }
        if ($form->get('membernumbergt')->getData()) {
            $qb = $qb->andWhere('o.member_number > :membernumbergt')
                ->setParameter('membernumbergt', $form->get('membernumbergt')->getData());
        }
        if ($form->get('membernumberlt')->getData()) {
            $qb = $qb->andWhere('o.member_number < :membernumberlt')
                ->setParameter('membernumberlt', $form->get('membernumberlt')->getData());
        }
        if ($form->get('membernumberdiff')->getData()) {
            $list  = explode(', ', $form->get('membernumberdiff')->getData());
            if (count($list)>1) {
                $qb = $qb->andWhere('o.member_number NOT IN (:membernumberdiff)')
                    ->setParameter('membernumberdiff', $list);
            } else {
                $qb = $qb->andWhere('o.member_number != :membernumberdiff')
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

        $join_formations = false;
        if ($form->get('formations')->getData() && count($form->get('formations')->getData())) {
            if (($form->get('or_and_exp_formations')->getData() > 0) && (count($form->get('formations')->getData()) > 1)) { // AND not OR
                $formations = $form->get('formations')->getData();
                $ids_groups = array();
                foreach ($formations as $formation) {
                    $tmp_qb = clone $qb;
                    $tmp_qb = $tmp_qb->leftjoin("b.formations", "ro")
                        ->andWhere('ro.id IN (:rid)')
                        ->setParameter('rid', $formation );
                    $ids_groups[] = $tmp_qb->select('DISTINCT o.id')->getQuery()->getArrayResult();
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
                $qb = $qb->andWhere('o.id IN (:all_formations)')
                    ->setParameter('all_formations', $ids);
            } else {
                $qb = $qb->leftjoin("b.formations", "ro")
                    ->andWhere('ro.id IN (:rids)')
                    ->setParameter('rids', $form->get('formations')->getData());
                $join_formations = true;
            }
        }
        $join_commissions = false;
        if ($form->get('commissions')->getData() && count($form->get('commissions')->getData())) {
            $qb = $qb->leftjoin("b.commissions", "c")
                ->andWhere('c.id IN (:cids)')
                ->setParameter('cids', $form->get('commissions')->getData() );
            $join_commissions = true;
        }
        if ($form->get('not_formations')->getData() && count($form->get('not_formations')->getData())) {
            $nrqb = clone $qb;
            if (!$join_formations) {
                $nrqb = $nrqb->leftjoin("b.formations", "ro")
                    ->andWhere('ro.id IN (:rids)');
            }
            $nrqb->setParameter('rids', $form->get('not_formations')->getData() );
            $subQuery = $nrqb->select('DISTINCT o.id')->getQuery()->getArrayResult();

            if (count($subQuery)) {
                $qb = $qb->andWhere('o.id NOT IN (:subQueryformations)')
                    ->setParameter('subQueryformations', $subQuery);
            }

        }
        if ($form->get('not_commissions')->getData() && count($form->get('not_commissions')->getData())) {
            $ncqb = clone $qb;
            if (!$join_commissions) {
                $ncqb = $ncqb->leftjoin("b.commissions", "c")
                    ->andWhere('c.id IN (:cids)');
            }
            $ncqb->setParameter('cids', $form->get('not_commissions')->getData() );
            $subQuery = $ncqb->select('DISTINCT o.id')->getQuery()->getArrayResult();

            if (count($subQuery)) {
                $qb = $qb->andWhere('o.id NOT IN (:subQueryformations)')
                    ->setParameter('subQueryformations', $subQuery);
            }
        }
        if ($form->get('flying')->getData() > 0) {
            $qb = $qb->andWhere('b.flying = :flying')
                     ->setParameter('flying', $form->get('flying')->getData()-1);
        }
        return $qb;
    }

}
