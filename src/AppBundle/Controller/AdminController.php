<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Commission;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Role;
use AppBundle\Entity\User;
use AppBundle\Form\BeneficiaryType;
use AppBundle\Form\UserType;
use Doctrine\ORM\Tools\Pagination\Paginator;
use OAuth2\OAuth2;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("admin")
 * @Security("has_role('ROLE_ADMIN')")
 */
class AdminController extends Controller
{
    /**
     * Admin panel
     *
     * @Route("/", name="admin")
     * @Method("GET")
     */
    public function indexAction()
    {
        return $this->render('admin/index.html.twig');
    }

    public function getSearchForm(){
        $fb =  $this->createFormBuilder();
        $fb->add('withdrawn', ChoiceType::class, array('label' => 'fermé','required' => false,'choices'  => array(
                'fermé' => 2,
                'ouvert' => 1,
            )))
            ->add('enabled', ChoiceType::class, array('label' => 'activé','required' => false,'choices'  => array(
                'activé' => 2,
                'Non activé' => 1,
            )))
            ->add('frozen', ChoiceType::class, array('label' => 'gelé','required' => false,'choices'  => array(
                'gelé' => 2,
                'Non gelé' => 1,
            )))
            ->add('membernumber', IntegerType::class, array('label' => '# =','required' => false))
            ->add('membernumbergt', IntegerType::class, array('label' => '# >','required' => false))
            ->add('membernumberlt', IntegerType::class, array('label' => '# <','required' => false))
            ->add('registrationdate', TextType::class, array('label' => 'le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('registrationdategt', TextType::class, array('label' => 'après le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('registrationdatelt', TextType::class, array('label' => 'avant le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('lastregistrationdate', TextType::class, array('label' => 'le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('lastregistrationdategt', TextType::class, array('label' => 'après le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('lastregistrationdatelt', TextType::class, array('label' => 'avant le','required' => false, 'attr' => array( 'class' => 'datepicker')))
            ->add('username', TextType::class, array('label' => 'username','required' => false))
            ->add('firstname', TextType::class, array('label' => 'prénom','required' => false))
            ->add('lastname', TextType::class, array('label' => 'nom','required' => false))
            ->add('email', TextType::class, array('label' => 'email','required' => false))
//            ->add('last_reg', DateType::class, array('label' => 'adhésion','required' => false))
            ->add('phone', ChoiceType::class, array('label' => 'téléphone','required' => false,'choices'  => array(
                'Renseigné' => 2,
                'Non renseigné' => 1,
            )))
            ->add('roles',EntityType::class, array(
                'class' => 'AppBundle:Role',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Avec le(s) Role(s)'
            ))
            ->add('commissions',EntityType::class, array(
                'class' => 'AppBundle:Commission',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Dans la/les commissions(s)'
            ))
            ->add('not_roles',EntityType::class, array(
                'class' => 'AppBundle:Role',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Sans le(s) Role(s)'
            ))
            ->add('not_commissions',EntityType::class, array(
                'class' => 'AppBundle:Commission',
                'choice_label'     => 'name',
                'multiple'     => true,
                'required' => false,
                'label'=>'Hors de la/les Commissions(s)'
            ))
            ->add('action', HiddenType::class,array())
            ->add('page', HiddenType::class,array())
            ->add('dir', HiddenType::class,array())
            ->add('sort', HiddenType::class,array())
            ->add('submit', SubmitType::class, array('label' => 'Filtrer','attr' => array('class' => 'btn','value' => 'show')))
            ->add('csv', SubmitType::class, array('label' => 'CSV','attr' => array('class' => 'btn','value' => 'csv')))
            ->add('mail', SubmitType::class, array('label' => 'Mail','attr' => array('class' => 'btn','value' => 'mail')));
        return $fb->getForm();
    }

    public function initSearchQuery(){
        $em = $this->getDoctrine()->getManager();
        $qb = $em->getRepository("AppBundle:User")->createQueryBuilder('o');
        $qb = $qb->leftJoin("o.beneficiaries", "b")->addSelect("b")
            ->leftJoin("o.lastRegistration", "lr")->addSelect("lr")
            ->leftJoin("o.registrations", "r")->addSelect("r");
        $qb = $qb->andWhere('o.member_number > 0'); //do not include admin user
        return $qb;
    }

    public function processSearchFormData($form,$qb){
        if ($form->get('withdrawn')->getData() > 0){
            $qb = $qb->andWhere('o.withdrawn = :withdrawn')
                ->setParameter('withdrawn', $form->get('withdrawn')->getData()-1);
        }
        if ($form->get('enabled')->getData() > 0){
            $qb = $qb->andWhere('o.enabled = :enabled')
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
        if ($form->get('lastregistrationdate')->getData()){
            $qb = $qb->andWhere('lr.date LIKE :lastregistrationdate')
                ->setParameter('lastregistrationdate', $form->get('lastregistrationdate')->getData().'%');
        }
        if ($form->get('lastregistrationdategt')->getData()){
            $qb = $qb->andWhere('lr.date > :lastregistrationdategt')
                ->setParameter('lastregistrationdategt', $form->get('lastregistrationdategt')->getData());
        }
        if ($form->get('lastregistrationdatelt')->getData()){
            $qb = $qb->andWhere('lr.date < :lastregistrationdatelt')
                ->setParameter('lastregistrationdatelt', $form->get('lastregistrationdatelt')->getData());
        }
        if ($form->get('membernumber')->getData()){
            $qb = $qb->andWhere('o.member_number = :membernumber')
                ->setParameter('membernumber', $form->get('membernumber')->getData());
        }
        if ($form->get('membernumbergt')->getData()){
            $qb = $qb->andWhere('o.member_number > :membernumbergt')
                ->setParameter('membernumbergt', $form->get('membernumbergt')->getData());
        }
        if ($form->get('membernumberlt')->getData()){
            $qb = $qb->andWhere('o.member_number < :membernumberlt')
                ->setParameter('membernumberlt', $form->get('membernumberlt')->getData());
        }
        if ($form->get('username')->getData()){
            $qb = $qb->andWhere('o.username LIKE :username')
                ->setParameter('username', '%'.$form->get('username')->getData().'%');
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
            $qb = $qb->andWhere('b.email LIKE :email')
                ->setParameter('email', '%'.$form->get('email')->getData().'%');
        }

        $role_left_join = false;
        $commission_left_join = false;
        if ($form->get('roles')->getData() && count($form->get('roles')->getData())){
            $qb = $qb->leftJoin("b.roles", "ro")->addSelect("ro")
                ->andWhere('ro.id IN (:rids)')
                ->setParameter('rids',$form->get('roles')->getData() );
            $role_left_join = true;
        }
        if ($form->get('commissions')->getData() && count($form->get('commissions')->getData())){
            $qb = $qb->leftJoin("b.commissions", "c")->addSelect("c")
                ->andWhere('c.id IN (:cids)')
                ->setParameter('cids',$form->get('commissions')->getData() );
            $commission_left_join = true;
        }
        if ($form->get('not_roles')->getData() && count($form->get('not_roles')->getData())){
            if (!$role_left_join){
                $qb = $qb->leftJoin("b.roles", "ro")->addSelect("ro");
            }
            $qb = $qb->andWhere('ro.id NOT IN (:nrids)')
                ->setParameter('nrids',$form->get('not_roles')->getData() );

        }
        if ($form->get('not_commissions')->getData() && count($form->get('not_commissions')->getData())){
            if (!$commission_left_join){
                $qb = $qb->leftJoin("b.commissions", "c")->addSelect("c");
            }
            $qb = $qb->andWhere('c.id NOT IN (:ncids)')
                ->setParameter('ncids',$form->get('not_commissions')->getData() );
        }

        return $qb;
    }

    /**
     * Lists all user entities.
     *
     * @param Request $request
     * @return Response
     * @Route("/users", name="user_index")
     * @Method({"GET","POST"})
     */
    public function usersAction(Request $request)
    {

        $form = $this->getSearchForm();
        $form->handleRequest($request);

        $action = $form->get('action')->getData();

        $qb = $this->initSearchQuery();

        $page = 1;
        $order = 'ASC';
        $sort = 'o.member_number';

        if ($form->isSubmitted() && $form->isValid()) {

            if ($form->get('page')->getData() > 0){
                $page = $form->get('page')->getData();
            }
            if ($form->get('sort')->getData()){
                $sort = $form->get('sort')->getData();
            }
            if ($form->get('dir')->getData()){
                $order = $form->get('dir')->getData();
            }

            $qb = $this->processSearchFormData($form,$qb);


        }else{
            $form->get('sort')->setData($sort);
            $form->get('dir')->setData($order);
        }

        $limit = 25;
        $qb2 = clone $qb;
        $max = $qb2->select('count(DISTINCT o.id)')->getQuery()->getSingleScalarResult();
        $nb_of_pages = intval($max/$limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;


        $qb = $qb->orderBy($sort, $order);
        if ($action == "csv"){
            $users = $qb->getQuery()->getResult();
            $return = '';
            $d = ','; // this is the default but i like to be explicit
            foreach($users as $user) {
                foreach ($user->getBeneficiaries() as $beneficiary)
                    $return .=
                        $beneficiary->getUser()->getMemberNumber().$d.
                        $beneficiary->getFirstname().$d.
                        $beneficiary->getLastname().$d.
                        $beneficiary->getEmail().$d.
                        $beneficiary->getPhone().
                        "\n";
            }
            return new Response($return, 200, array(
                'Content-Encoding: UTF-8',
                'Content-Type' => 'application/force-download; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="emails_'.date('dmyhis').'.csv"'
            ));
        }else if($action === "mail") {
            return $this->redirectToRoute('mail_edit', [
                'request' => $request
            ], 307);
        }else{
            $qb = $qb->setFirstResult( ($page - 1)*$limit )->setMaxResults( $limit );
            $users = new Paginator($qb->getQuery());
        }

        return $this->render('admin/user/list.html.twig', array(
            'users' => $users,
            'form' => $form->createView(),
            'nb_of_result' => $max,
            'page'=>$page,
            'nb_of_pages'=>$nb_of_pages
        ));
    }

    /**
     * Registrations list
     *
     * @Route("/registrations", name="admin_registrations")
     * @Method("GET")
     */
    public function registrationsAction(Request $request)
    {
        if (!($page = $request->get('page')))
            $page = 1;
        $limit = 50;
        $max = $this->getDoctrine()->getManager()->createQueryBuilder()->from('AppBundle\Entity\Registration', 'u')
            ->select('count(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $nb_of_pages = intval($max/$limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        $registrations = $this->getDoctrine()->getManager()
            ->getRepository('AppBundle:Registration')
            ->findBy(array(),array('created_at' => 'DESC','date' => 'DESC'),$limit,($page-1)*$limit);
        $delete_forms = array();
        foreach ($registrations as $registration){
            $delete_forms[$registration->getId()] = $this->getRegistrationDeleteForm($registration)->createView();
        }
        return $this->render('admin/registrations.html.twig',array('registrations'=>$registrations,'delete_forms'=>$delete_forms,'page'=>$page,'nb_of_pages'=>$nb_of_pages));
    }

    /**
     * remove registration
     *
     * @Route("/remove_registration/{id}", name="admin_registration_remove")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeRegistrationAction(Request $request,Registration $registration){
        $session = new Session();
        $form = $this->getRegistrationDeleteForm($registration);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (count($registration->getUser()->getRegistrations()) === 1 && $registration === $registration->getUser()->getLastRegistration()){
                $session->getFlashBag()->add('error', 'C\'est la seule adhésion de cette adhérent, corrigez là plutôt que de la supprimer');
            }else{
                $em = $this->getDoctrine()->getManager();
                if ($registration->getUser()){
                    $registration->getUser()->removeRegistration($registration);
                    $em->persist($registration->getUser());
                }
                if ($registration->getRegistrar()){
                    $registration->getRegistrar()->removeRecordedRegistration($registration);
                    $em->persist($registration->getRegistrar());
                }
                $em->remove($registration);
                $em->flush();
                $session->getFlashBag()->add('success', 'L\'adhésion a bien été supprimée !');
            }
        }
        return $this->redirectToRoute('admin_registrations');
    }

    /**
     * @param Registration $registration
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getRegistrationDeleteForm(Registration $registration){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_registration_remove', array('id' => $registration->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Registrations correction
     *
     * @Route("/registrations_fix", name="admin_registrations_fix")
     * @Method("GET")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function registrationsFixAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository('AppBundle:User')->findAll();
        foreach ($users as $user){
            if ($user->getRegistrations()->count() && $user->getRegistrations()->first())
                $user->setLastRegistration($user->getRegistrations()->first());
            else
                $user->setLastRegistration();
            $em->persist($user);
            foreach ($user->getRegistrations() as $registration){
                if ($registration->getCreatedAt()->format('Y') < 0){
                    $registration->setCreatedAt($registration->getDate());
                    $em->persist($registration);
                }
            }
        }
        $em->flush();
        $session = new Session();
        $session->getFlashBag()->add('success', 'all registrations dates fixed');
        return $this->redirectToRoute('admin');
    }

    /**
     * Registrations correction
     *
     * @Route("/status_fix", name="admin_status_fix")
     * @Method("GET")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function statusFixAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository('AppBundle:User')->findAll();
        foreach ($users as $user){
            if ($user->getFrozen() === null)
                $user->setFrozen(false);
            if ($user->getWithdrawn() === null)
                $user->setWithdrawn(false);
            $em->persist($user);
        }
        $em->flush();
        $session = new Session();
        $session->getFlashBag()->add('success', 'all status fixed');
        return $this->redirectToRoute('admin');
    }

    /**
     * Phone correction
     *
     * @Route("/phone_fix", name="admin_phone_fix")
     * @Method("GET")
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function phoneFixAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository('AppBundle:User')->findAll();
        foreach ($users as $user){
            foreach ($user->getBeneficiaries() as $beneficiary){
                $phone = $beneficiary->getPhone();
                // 0 missing at start ?
                $re = '/^[123456789][0-9]{8}$/';
                preg_match_all($re, $phone, $matches, PREG_SET_ORDER, 0);
                if(count($matches) >= 1){
                    $phone = '0'.$phone;
                }
                // to many 0 at start ?
                $re = '/^[0][0]([0-9]*)$/';
                preg_match_all($re, $phone, $matches, PREG_SET_ORDER, 0);
                if(count($matches) >= 1){
                    $phone = '0'.$matches[0][1];
                }
                //space ?
                $phone = str_replace(' ','',$phone);
                //dot ?
                $phone = str_replace('.','',$phone);
                //
                if (!($phone === $beneficiary->getPhone())){
                    $beneficiary->setPhone($phone);
                    $em->persist($beneficiary);
                }
            }
        }
        $em->flush();
        $session = new Session();
        $session->getFlashBag()->add('success', 'all phone fixed');
        return $this->redirectToRoute('admin');
    }

    /**
     * export all emails of members (including beneficiary)
     *
     * @Route("/emails_csv", name="admin_emails_csv")
     * @Method({"GET"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function exportEmails(Request $request){
        $beneficiaries = $this->getDoctrine()->getRepository("AppBundle:Beneficiary")->findAll();
        $return = '';
        if($beneficiaries) {
            $d = ','; // this is the default but i like to be explicit
            $e = '"'; // this is the default but i like to be explicit
            foreach($beneficiaries as $beneficiary) {
                if (!$beneficiary->getUser()->isWithdrawn()){
                    $r = preg_match_all('/(membres\\+[0-9]+@lelefan\\.org)/i', $beneficiary->getEmail(), $matches, PREG_SET_ORDER, 0); //todo put regex in conf
                    if (!count($matches)&&filter_var($beneficiary->getEmail(),FILTER_VALIDATE_EMAIL)) { //was not a temp mail
                        $return .= $beneficiary->getFirstname().$d.$beneficiary->getLastname().$d.$beneficiary->getEmail()."\n";
                    }
                }
            }
        }
        return new Response($return, 200, array(
            'Content-Encoding: UTF-8',
            'Content-Type' => 'application/force-download; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="emails_'.date('dmyhis').'.csv"'
        ));
    }

    /**
     * Join two user
     *
     * @Route("/join", name="user_join")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function joinAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('from_text', TextType::class, array('label' => 'Adhérent a joindre'))
            ->add('dest_text', TextType::class, array('label' => 'au compte de l\'adhérent'))
            ->add('join', SubmitType::class, array('label' => 'Joindre les deux comptes','attr' => array('class' => 'btn')))
            ->getForm();
        $form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();

        if ($form->isSubmitted() && $form->isValid()) {
            $session = new Session();
            $re = '/#([0-9]+).*/';
            $str = $form->get('from_text')->getData()."\n".$form->get('dest_text')->getData();
            preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
            if (count($matches)>=2){
                $fromUser = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>$matches[0][1]));
                if ($fromUser){
                    $destUser = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>$matches[1][1]));
                    if ($destUser){
                        foreach ($fromUser->getBeneficiaries() as $beneficiary){
                            $destUser->addBeneficiary($beneficiary); //in
                            $fromUser->removeBeneficiary($beneficiary); //out
                            $beneficiary->setUser($destUser);
                            $em->persist($beneficiary);
                        }
                        $em->persist($destUser);
                        $em->flush();
                        $fromUser->setMainBeneficiary(null);
                        $em->remove($fromUser);
                        $em->flush();

                        $session->getFlashBag()->add('success', 'Les deux adhérents ont bien été fusionnés');

                        return $this->redirectToRoute('user_edit',array('username'=>$destUser->getUsername()));
                    }else{
                        $session->getFlashBag()->add('error', 'impossible de trouver le compte de destination');
                    }
                }else{
                    $session->getFlashBag()->add('error', 'impossible de trouver le compte à lier');
                }
            }

        }

        $users = $em->getRepository('AppBundle:User')->findAll(); //todo exclude closed
        return $this->render('admin/user/join.html.twig',array('form'=>$form->createView(),'users'=>$users));
    }


    /**
     * Import from CSV
     *
     * @Route("/importcsv", name="user_import_csv")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function csvImportAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('submitFile', FileType::class, array('label' => 'File to Submit'))
            ->add('delimiter', TextType::class, array('label' => 'delimiter','attr' => array(
                'placeholder' => ',',
            ),'data'=>','))
            ->add('persist',CheckboxType::class,array('required'=>false,'label'=>'Sauver en base'))
            ->add('compute', SubmitType::class, array('label' => 'compute'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {

            // Get file
            $file = $form->get('submitFile');
            $delimiter = ($form->get('delimiter'))? $form->get('delimiter')->getData() : ',';
            $persist = ($form->get('persist'))? $form->get('persist')->getData() : false;

            // Your csv file here when you hit submit button
            $data = $file->getData();
            $filename = $file->getData()->getPathName();

            $row = 1;
            $lastdate = DateTime::createFromFormat('d/m/Y', '04/05/2016');
            $em = $this->getDoctrine()->getManager();
            $return = array();
            $usernames = array();
            $emails = array();
            if (($handle = fopen($filename, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE /*
                    && $row<10 //*/
                ) {
                    /*
                     Array
                    (
                    [0] => compare
                    [1] => Date d'adhésion
                    [2] => Type Adhésion
                    [3] => Nom
                    [4] => Prénom
                    [5] => Adresse1
                    [6] => CP
                    [7] => Ville
                    [8] => Téléphone
                    [9] => Mail
                    [10] => Montant
                    [11] => Mode de réglement
                    [12] => A intégrer?
                    [13] => Renouvellement adhésion - Date
                    [14] => Montant
                    [15] => Mode de réglement
                    [16] => Qualité
                    [17] => Bénévole Ressource
                    [18] => Ambassadeur
                    [19] =>
                    )*/
                    preg_match_all('/^[0-9]+$/', $data[0], $matches, PREG_SET_ORDER, 0);
                    if (count($data)>11&&isset($data[3])&&isset($data[4])&&count($matches)&&strlen($data[3])>1&&strlen($data[4])>1){ // on ne traite que les colonnes qui commence par un numéro d'adhérent valide (entier)
                        $member_number = $data[0];
                        $user = $em->getRepository('AppBundle:User')->findOneBy(array("member_number"=>$member_number));
                        if ($user){
                            $mail = $data[9];
                            if (isset($data[9])&&filter_var($mail, FILTER_VALIDATE_EMAIL)&&($user->getEmail() != $mail)) {
                                $user_exist = $em->getRepository('AppBundle:User')->findOneBy(array("email"=>$mail));
                                if (!$user_exist){
                                    $user->setEmail($mail);
                                    if ($persist)
                                        $em->persist($user);
                                    $return[] = array($user,array("error","user with same member number already exist, email updated"));
                                }else{
                                    $return[] = array($user,array("error","user with same member number already exist, email change but already in use"));
                                }
                            }else{
                                $return[] = array($user,array("error","user with same member number already exist"));
                            }
                        } else {
                            $mail = $data[9];
                            $validator = $this->container->get('validator');
                            $constraints = array(
                                new EmailConstraint(),
                                new NotBlank()
                            );
                            $error = $validator->validate($mail, $constraints);
                            if ($error->count()){
                                $return[] = array($user,array("error","email is not valid (".$mail.")"));
                            }else{
                                $user = $em->getRepository('AppBundle:User')->findOneBy(array("email"=>$mail));
                                $already_registred = (isset($emails[$mail])) ? true : false;
                                if ($user||$already_registred)
                                    $return[] = array($user,array("error","user with same email already exist"));
                                else {
                                    $user = new User();
                                    $firstname = trim(preg_replace('/\s\s+/', ' ', $data[4]));
                                    $lastname = trim(preg_replace('/\s\s+/', ' ', $data[3]));
                                    $username = User::makeUsername($firstname,$lastname);
                                    $qb = $em->createQueryBuilder();
                                    $users = $qb->select('u')->from('AppBundle\Entity\User', 'u')
                                        ->where( $qb->expr()->like('u.username', $qb->expr()->literal($username.'%')) )
                                        ->getQuery()
                                        ->getResult();
                                    //$users = $em->getRepository('AppBundle:User')->findBy(array("username"=>$username));
                                    $already_registred = (isset($usernames[$username])) ? $usernames[$username]  : 0;
                                    if (count($users)||$already_registred){
                                        $username = User::makeUsername($firstname,$lastname,count($users)+1+$already_registred);
                                    }
                                    if (strlen($username)>3){
                                        $user->setUsername($username);
                                        $user->setEmail($mail);
                                        $user->setMemberNumber($member_number);
                                        $password = User::randomPassword();
                                        $user->setPassword($password);
                                        //beneficiary
                                        $beneficiary = new Beneficiary();
                                        $beneficiary->setFirstname($firstname);
                                        $beneficiary->setLastname($lastname);
                                        $beneficiary->setPhone($data[8]);
                                        $beneficiary->setEmail($mail);
                                        $beneficiary->setAmbassador(($data[8]!='')&&$data[8]=='1');
                                        $beneficiary->setExpert(false);//default all false
                                        $beneficiary->setUser($user);
                                        $user->setMainBeneficiary($beneficiary);
                                        //address
                                        $address = new Address();
                                        $address->setStreet1($data[5]);
                                        $address->setStreet2('');
                                        $address->setZipcode($data[6]);
                                        $address->setCity($data[7]);
                                        $address->setUser($user);
                                        $user->setAddress($address);
                                        //registration
                                        $registration = new Registration();
                                        $date = $data[1];
                                        if (!$date)
                                            $date = $lastdate;
                                        else {
                                            $date = DateTime::createFromFormat('d/m/Y', $date);
                                            if (!$date)
                                                $date = $lastdate;
                                        }
                                        $lastdate = $date;
                                        $registration->setDate($date); //Y-m-d H:i:s
                                        $registration->setAmount(intval($data[10]));
                                        $reglement = $data[11];
                                        if (!$reglement&&strtolower($data[2])=='site')
                                            $reglement = 'cb';
                                        switch ($reglement){
                                            case 'chq' :
                                            case 'CHQ' :
                                            case 'ch' :
                                                $registration->setMode(Registration::TYPE_CHECK);
                                                break;
                                            case 'EPP':
                                            case 'ESP':
                                            case 'esp':
                                            case 'Espèce':
                                                $registration->setMode(Registration::TYPE_CASH);
                                                break;
                                            case 'Site':
                                            case 'site':
                                            case 'cb':
                                                $registration->setMode(Registration::TYPE_CREDIT_CARD);
                                                break;
                                            default:
                                                $registration->setMode(Registration::TYPE_DEFAULT);
                                        }
                                        $registration->setUser($user);
                                        $user->addRegistration($registration);
                                        $return[] = array($user,array("check","user added"));
                                        $usernames[$user->getUsername()] = (isset($usernames[$user->getUsername()])) ? $usernames[$user->getUsername()] +1 : 1;
                                        $emails[$user->getEmail()] = true;
                                        if ($persist)
                                            $em->persist($user);
                                    }else{
                                        $return[] = array($user,array("error","username build to short"));
                                    }
                                }
                            }
                        }
                    }
                    $row++;
                }
                fclose($handle);
                $em->flush();
            }

            if ($persist){
                $request->getSession()->getFlashBag()->add('notice', 'Le fichier a été traité complétement.');
                return $this->redirectToRoute('user_index');
            }else{
                return $this->render('admin/user/test_import.html.twig', array(
                    'users' => $return,
                ));
            }

        }

        return $this->render('admin/user/import.html.twig', array(
            'form' => $form->createView(),
        ));
    }
}
