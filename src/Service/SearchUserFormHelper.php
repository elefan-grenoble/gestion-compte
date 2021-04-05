<?php
//DependencyInjection/SearchUserFormHelper.php
namespace App\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilder;

class SearchUserFormHelper {

    public function getSearchForm($formBuilder,$params_string = '', $ambassador = false){
        $params = array();
        parse_str($params_string,$params);
        $formBuilder->add('withdrawn', ChoiceType::class, array('label' => 'fermé','required' => false,'choices'  => array(
            'fermé' => 2,
            'ouvert' => 1,
        )));
        if (!$ambassador) {
            $formBuilder->add('enabled', ChoiceType::class, array('label' => 'activé','required' => false,'choices'  => array(
                'activé' => 2,
                'Non activé' => 1,
            )));
        }
        $formBuilder->add('frozen', ChoiceType::class, array('label' => 'gelé','required' => false,'choices'  => array(
                'gelé' => 2,
                'Non gelé' => 1,
            )));

        if ($params && isset($params['membernumber']) && $params['membernumber'])
            $formBuilder->add('membernumber', TextType::class, array('label' => '# =','required' => false, 'attr' => array('value' => $params['membernumber'])));
        else
            $formBuilder->add('membernumber', TextType::class, array('label' => '# =','required' => false));

        $formBuilder->add('membernumbergt', IntegerType::class, array('label' => '# >','required' => false))
            ->add('membernumberlt', IntegerType::class, array('label' => '# <','required' => false));
        if (!$ambassador) {
            $formBuilder->add('membernumberdiff', TextType::class, array('label' => '# <>','required' => false))
            ->add('registrationdate', TextType::class, array('label' => 'le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('registrationdategt', TextType::class, array('label' => 'après le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('registrationdatelt', TextType::class, array('label' => 'avant le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('lastregistrationdate', TextType::class, array('label' => 'le','required' => false, 'attr' => array( 'class' => 'datepicker')));
        }
        $formBuilder->add('lastregistrationdategt', TextType::class, array('label' => 'après le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('lastregistrationdatelt', TextType::class, array('label' => 'avant le','required' => false, 'attr' => array( 'class' => 'datepicker')));
        if (!$ambassador) {
            $formBuilder->add('username', TextType::class, array('label' => 'username','required' => false));
        }
        $formBuilder->add('compteurlt', NumberType::class, array('label' => 'max','required' => false))
            ->add('compteurgt', NumberType::class, array('label' => 'min','required' => false))
            ->add('firstname', TextType::class, array('label' => 'prénom','required' => false))
            ->add('lastname', TextType::class, array('label' => 'nom','required' => false))
            ->add('email', TextType::class, array('label' => 'email','required' => false));
        if (!$ambassador) {
            $formBuilder->add('phone', ChoiceType::class, array('label' => 'téléphone','required' => false,'choices'  => array(
                'Renseigné' => 2,
                'Non renseigné' => 1,
            )))
            ->add('formations', EntityType::class, array(
                'class' => 'App:Formation',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Avec le(s) Role(s)'
            ))
            ->add('or_and_exp_formations', ChoiceType::class, array('label' => 'Tous ?','required' => true,'choices'  => array(
                'Au moins un role' => 1,
                'Tous ces roles' => 2,
            )))
            ->add('commissions',EntityType::class, array(
                'class' => 'App:Commission',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Dans la/les commissions(s)'
            ))
            ->add('not_formations',EntityType::class, array(
                'class' => 'App:Formation',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Sans le(s) Role(s)'
            ))
            ->add('not_commissions',EntityType::class, array(
                'class' => 'App:Commission',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Hors de la/les Commissions(s)'
            ));
        }
        $formBuilder->add('action', HiddenType::class,array())
            ->add('page', HiddenType::class,array())
            ->add('dir', HiddenType::class,array())
            ->add('sort', HiddenType::class,array())
            ->add('submit', SubmitType::class, array('label' => 'Filtrer','attr' => array('class' => 'btn','value' => 'show')));
        if (!$ambassador) {
            $formBuilder->add('csv', SubmitType::class, array('label' => 'Export CSV','attr' => array('class' => 'btn','value' => 'csv')))
            ->add('mail', SubmitType::class, array('label' => 'Envoyer un mail','attr' => array('class' => 'btn','value' => 'mail')));
        }
        return $formBuilder->getForm();
    }

    /**
     * @param EntityManager $doctrineManager
     * @return QueryBuilder
     */
    public function initSearchQuery($doctrineManager){
        /** @var QueryBuilder $qb */
        $qb = $doctrineManager->getRepository("App:Membership")->createQueryBuilder('o');
        $qb = $qb->leftJoin("o.beneficiaries", "b")->addSelect("b")
            ->leftJoin("o.registrations", "r")->addSelect("r")
            ->leftJoin("b.user", "u")->addSelect("u");
        $qb = $qb->andWhere('o.member_number > 0'); //do not include admin user
        return $qb;
    }

    /**
     * @param FormBuilder $form
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function processSearchFormAmbassadorData($form,&$qb,$session,$type){
        $lastYear = new \DateTime('last year');
        if ($form->get('withdrawn')->getData() > 0){
            $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                     ->setParameter('withdrawn', $form->get('withdrawn')->getData()-1);
        } else {
            $qb = $qb->andWhere('o.withdrawn = 0');
        }
        if ($form->get('frozen')->getData() > 0){
            $qb = $qb->andWhere('o.frozen = :frozen')
                     ->setParameter('frozen', $form->get('frozen')->getData()-1);
        } elseif ($type == "shifttimelog") {
            $qb = $qb->andWhere('o.frozen = 0');
        }
        if ($form->get('compteurlt')->getData()){
            $compteurlt = $form->get('compteurlt')->getData();
            if ($type == "shifttimelog" && $compteurlt > 0){
                $session->getFlashBag()->add('warning','Oups, cet outil n\'est pas conçu pour rechercher des membres à jour sur leurs créneaux');
                $compteurlt = 0;
            }
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t.membership) FROM AppBundle\Entity\TimeLog t GROUP BY t.membership HAVING SUM(t.time) < :compteurlt * 60)')
                ->setParameter('compteurlt', $compteurlt);
        } elseif ($type == "shifttimelog"){
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t.membership) FROM AppBundle\Entity\TimeLog t GROUP BY t.membership HAVING SUM(t.time) < 0)');
        }
        if ($form->get('compteurgt')->getData()){
            $compteurgt = $form->get('compteurgt')->getData();
            if ($type == "shifttimelog" && $compteurgt >= 0){
                $session->getFlashBag()->add('warning','Oups, cet outil n\'est pas conçu pour rechercher des membres à jour sur leurs créneaux');
                $compteurgt = 0;
            }
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t1.membership) FROM AppBundle\Entity\TimeLog t1 GROUP BY t1.membership HAVING SUM(t1.time) > :compteurgt * 60)')
                ->setParameter('compteurgt', $compteurgt);
        }
        if ($form->get('lastregistrationdategt')->getData()){
            $date = $form->get('lastregistrationdategt')->getData();
            $datetime = \DateTime::createFromFormat('Y-m-d', $date);
            if ($type == "membership" && $datetime > $lastYear){
                $session->getFlashBag()->add('warning','Oups, cet outil n\'est pas conçu pour rechercher des membres à jour sur leurs adhésions');
                $date = $lastYear->format('Y-m-d') ;
            }
            $qb = $qb->andWhere('r.date > :lastregistrationdategt')
                     ->setParameter('lastregistrationdategt', $date);
        }
        if ($form->get('lastregistrationdatelt')->getData()){
            $date = $form->get('lastregistrationdatelt')->getData();
            $datetime = \DateTime::createFromFormat('Y-m-d', $date);
            if ($type == "membership" && $datetime > $lastYear){
                $session->getFlashBag()->add('warning','Oups, cet outil n\'est pas conçu pour rechercher des membres à jour sur leurs adhésions');
                $date = $lastYear->format('Y-m-d') ;
            }
            $qb = $qb->andWhere('r.date < :lastregistrationdatelt')
                     ->setParameter('lastregistrationdatelt', $date);
        } elseif ($type == "membership"){
            $date = $lastYear->format('Y-m-d') ;
            $qb = $qb->andWhere('r.date < :lastregistrationdatelt')
                     ->setParameter('lastregistrationdatelt', $date);
        }
        if ($form->get('membernumber')->getData()){
            $qb = $qb->andWhere('o.member_number = :membernumber')
                     ->setParameter('membernumber', $form->get('membernumber')->getData());
        }
        if ($form->get('membernumbergt')->getData()){
            $qb = $qb->andWhere('o.member_number > :membernumbergt')
                     ->setParameter('membernumbergt', $form->get('membernumbergt')->getData());
        }
        return $qb;
    }

    /**
     * @param FormBuilder $form
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function processSearchFormData($form,&$qb){
        if ($form->get('withdrawn')->getData() > 0){
            $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $form->get('withdrawn')->getData()-1);
        }
        if ($form->get('enabled')->getData() > 0){
            $qb = $qb->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', $form->get('enabled')->getData()-1);
        }
        if ($form->get('frozen')->getData() > 0){
            $qb = $qb->andWhere('o.frozen = :frozen')
                ->setParameter('frozen', $form->get('frozen')->getData()-1);
        }

        if ($form->get('phone')->getData() > 0){
            if ($form->get('phone')->getData() == 1) { //non renseigné
                $qb = $qb->andWhere('b.phone < 100000000');
            }else{
                $qb = $qb->andWhere('b.phone > 100000000');
            }
        }

        if ($form->get('registrationdate')->getData()){
            $qb = $qb->andWhere('r.date LIKE :registrationdate')
                ->setParameter('registrationdate', $form->get('registrationdate')->getData().'%');
        }
        if ($form->get('registrationdategt')->getData()){
            $qb = $qb->andWhere('r.date > :registrationdategt')
                ->setParameter('registrationdategt', $form->get('registrationdategt')->getData());
        }
        if ($form->get('registrationdatelt')->getData()){
            $qb = $qb->andWhere('r.date < :registrationdatelt')
                ->setParameter('registrationdatelt', $form->get('registrationdatelt')->getData());
        }
        if ($form->get('compteurlt')->getData()){
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t.membership) FROM AppBundle\Entity\TimeLog t GROUP BY t.membership HAVING SUM(t.time) < :compteurlt * 60)')
                ->setParameter('compteurlt', $form->get('compteurlt')->getData());
        }
        if ($form->get('compteurgt')->getData()){
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t1.membership) FROM AppBundle\Entity\TimeLog t1 GROUP BY t1.membership HAVING SUM(t1.time) > :compteurgt * 60)')
                ->setParameter('compteurgt', $form->get('compteurgt')->getData());
        }
        if ($form->get('lastregistrationdate')->getData()){
            $qb = $qb
                ->leftJoin("o.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
                ->andWhere('lr.id IS NULL')
                ->andWhere('r.date LIKE :lastregistrationdate')
                ->setParameter('lastregistrationdate', $form->get('lastregistrationdate')->getData().'%');
        }
        if ($form->get('lastregistrationdategt')->getData()){
            $qb = $qb
                ->leftJoin("o.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
                ->andWhere('lr.id IS NULL')
                ->andWhere('r.date > :lastregistrationdategt')
                ->setParameter('lastregistrationdategt', $form->get('lastregistrationdategt')->getData());
        }
        if ($form->get('lastregistrationdatelt')->getData()){
            $qb = $qb
                ->leftJoin("o.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
                ->andWhere('lr.id IS NULL')
                ->andWhere('r.date < :lastregistrationdatelt')
                ->setParameter('lastregistrationdatelt', $form->get('lastregistrationdatelt')->getData());
        }
        if ($form->get('membernumber')->getData()){
            $list  = explode(',',$form->get('membernumber')->getData());
            if (count($list)>1){
                $qb = $qb->andWhere('o.member_number IN (:membernumber)')
                    ->setParameter('membernumber', $list);
            }else{
                $qb = $qb->andWhere('o.member_number = :membernumber')
                    ->setParameter('membernumber', $form->get('membernumber')->getData());
            }
        }
        if ($form->get('membernumbergt')->getData()){
            $qb = $qb->andWhere('o.member_number > :membernumbergt')
                ->setParameter('membernumbergt', $form->get('membernumbergt')->getData());
        }
        if ($form->get('membernumberlt')->getData()){
            $qb = $qb->andWhere('o.member_number < :membernumberlt')
                ->setParameter('membernumberlt', $form->get('membernumberlt')->getData());
        }
        if ($form->get('membernumberdiff')->getData()){
            $list  = explode(',',$form->get('membernumberdiff')->getData());
            if (count($list)>1){
                $qb = $qb->andWhere('o.member_number NOT IN (:membernumberdiff)')
                    ->setParameter('membernumberdiff', $list);
            }else{
                $qb = $qb->andWhere('o.member_number != :membernumberdiff')
                    ->setParameter('membernumberdiff', $form->get('membernumberdiff')->getData());
            }
        }
        if ($form->get('username')->getData()){
            $list  = explode(',',$form->get('username')->getData());
            if (count($list)>1){
                $qb = $qb->andWhere('u.username IN (:usernames)')
                    ->setParameter('usernames', $list);
            }else{
                $qb = $qb->andWhere('u.username LIKE :username')
                    ->setParameter('username', '%'.$form->get('username')->getData().'%');
            }
        }
        if ($form->get('firstname')->getData()){
            $qb = $qb->andWhere('b.firstname LIKE :firstname')
                ->setParameter('firstname', '%'.$form->get('firstname')->getData().'%');
        }
        if ($form->get('lastname')->getData()){
            $qb = $qb->andWhere('b.lastname LIKE :lastname')
                ->setParameter('lastname', '%'.$form->get('lastname')->getData().'%');
        }
        if ($form->get('email')->getData()){
            $list  = explode(',',$form->get('email')->getData());
            if (count($list)>1){
                $qb = $qb->andWhere('u.email IN (:emails)')
                    ->setParameter('emails', $list);
            }else{
                $qb = $qb->andWhere('u.email LIKE :email')
                    ->setParameter('email', '%'.$form->get('email')->getData().'%');
            }
        }
        $join_formations = false;
        if ($form->get('formations')->getData() && count($form->get('formations')->getData())){
            if (($form->get('or_and_exp_formations')->getData() > 1) && (count($form->get('formations')->getData()) > 1)){ //AND not OR
                $formations = $form->get('formations')->getData();
                $ids_groups = array();
                foreach ($formations as $formation){
                    $tmp_qb = clone $qb;
                    $tmp_qb = $tmp_qb->leftjoin("b.formations", "ro")->addSelect("ro")
                        ->andWhere('ro.id IN (:rid)')
                        ->setParameter('rid',$formation );
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
            }else{
                $qb = $qb->leftjoin("b.formations", "ro")->addSelect("ro")
                    ->andWhere('ro.id IN (:rids)')
                    ->setParameter('rids',$form->get('formations')->getData() );
                $join_formations = true;
            }
        }
        $join_commissions = false;
        if ($form->get('commissions')->getData() && count($form->get('commissions')->getData())){
            $qb = $qb->leftjoin("b.commissions", "c")->addSelect("c")
                ->andWhere('c.id IN (:cids)')
                ->setParameter('cids',$form->get('commissions')->getData() );
            $join_commissions = true;
        }
        if ($form->get('not_formations')->getData() && count($form->get('not_formations')->getData())){
            $nrqb = clone $qb;
            if (!$join_formations){
                $nrqb = $nrqb->leftjoin("b.formations", "ro")->addSelect("ro")
                    ->andWhere('ro.id IN (:rids)');
            }
            $nrqb->setParameter('rids',$form->get('not_formations')->getData() );
            $subQuery = $nrqb->select('DISTINCT o.id')->getQuery()->getArrayResult();

            if (count($subQuery)){
                $qb = $qb->andWhere('o.id NOT IN (:subQueryformations)')
                    ->setParameter('subQueryformations', $subQuery);
            }

        }
        if ($form->get('not_commissions')->getData() && count($form->get('not_commissions')->getData())){
            $ncqb = clone $qb;
            if (!$join_commissions){
                $ncqb = $ncqb->leftjoin("b.commissions", "c")->addSelect("c")
                    ->andWhere('c.id IN (:cids)');
            }
            $ncqb->setParameter('cids',$form->get('not_commissions')->getData() );
            $subQuery = $ncqb->select('DISTINCT o.id')->getQuery()->getArrayResult();

            if (count($subQuery)){
                $qb = $qb->andWhere('o.id NOT IN (:subQueryformations)')
                    ->setParameter('subQueryformations', $subQuery);
            }
        }

        return $qb;
    }

    public function processSearchQueryData($params_string,&$qb){
        $params = array();
        parse_str($params_string,$params);
        if ($params && $params['membernumber']){
            $list  = explode(',',$params['membernumber']);
            if (count($list)>1){
                $qb = $qb->andWhere('o.member_number IN (:membernumber)')
                    ->setParameter('membernumber', $list);
            }else{
                $qb = $qb->andWhere('o.member_number = :membernumber')
                    ->setParameter('membernumber', $params['membernumber']);
            }
        }
        return $qb;
    }
}
