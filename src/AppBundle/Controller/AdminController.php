<?php

namespace AppBundle\Controller;

use AppBundle\Command\ImportUsersCommand;
use AppBundle\Entity\AbstractRegistration;
use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Commission;
use AppBundle\Entity\HelloassoPayment;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Formation;
use AppBundle\Entity\User;
use AppBundle\Event\HelloassoEvent;
use AppBundle\Form\BeneficiaryType;
use AppBundle\Form\RegistrationType;
use AppBundle\Service\SearchUserFormHelper;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use OAuth2\OAuth2;
use Ornicar\GravatarBundle\GravatarApi;
use Ornicar\GravatarBundle\Templating\Helper\GravatarHelper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
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
 * @Security("has_role('ROLE_USER_MANAGER')")
 */
class AdminController extends Controller
{
    /**
     * Admin panel
     *
     * @Route("/", name="admin")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        return $this->render('admin/index.html.twig');
    }

    /**
     * @Route("/search", name="search")
     * @Method("POST")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function searchAction(Request $request)
    {
        if ($request->isXMLHttpRequest()) {
            $key = $request->get('key');
            $return = array();

            $em = $this->getDoctrine()->getManager();

            $rsm = new ResultSetMappingBuilder($em);
            $rsm->addRootEntityFromClassMetadata('AppBundle:Beneficiary', 'b');

            $query = $em->createNativeQuery('SELECT b.* FROM beneficiary AS b LEFT JOIN fos_user as u ON u.id = b.user_id WHERE LOWER(CONCAT_WS(u.username,u.email,b.lastname,b.firstname)) LIKE :key', $rsm);

            $beneficiaries = $query->setParameter('key', '%' . $key . '%')
                ->getResult();

            foreach ($beneficiaries as $beneficiary) {
                if ($beneficiary->getUser()) {
                    $return[] = array(
                        'name' => $beneficiary->getAutocompleteLabelFull(),
                        'icon' => null,
                        'member_number' => $beneficiary->getMembership()->getMemberNumber(),
                        'id' => $beneficiary->getId()
                    );
                }
            }
            return new JsonResponse(array('count' => count($return), 'data' => array_values($return)));
        }

        return new Response('This is not ajax!', 400);
    }

    /**
     * Lists all user entities.
     *
     * @param Request $request , SearchUserFormHelper $formHelper
     * @return Response
     * @Route("/users", name="user_index")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_USER_MANAGER')")
     */
    public function usersAction(Request $request, SearchUserFormHelper $formHelper)
    {
        $form = $formHelper->getSearchForm($this->createFormBuilder(), $request->getQueryString());
        $form->handleRequest($request);

        $action = $form->get('action')->getData();

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager());

        $page = 1;
        $order = 'ASC';
        $sort = 'o.member_number';

        if ($form->isSubmitted() && $form->isValid()) {

            if ($form->get('page')->getData() > 0) {
                $page = $form->get('page')->getData();
            }
            if ($form->get('sort')->getData()) {
                $sort = $form->get('sort')->getData();
            }
            if ($form->get('dir')->getData()) {
                $order = $form->get('dir')->getData();
            }

            $formHelper->processSearchFormData($form, $qb);

        } else {
            $form->get('sort')->setData($sort);
            $form->get('dir')->setData($order);
        }

        $formHelper->processSearchQueryData($request->getQueryString(), $qb);

        $limit = 25;
        $qb2 = clone $qb;
        $max = $qb2->select('count(DISTINCT o.id)')->getQuery()->getSingleScalarResult();
        $nb_of_pages = intval($max / $limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;


        $qb = $qb->orderBy($sort, $order);
        if ($action == "csv") {
            $members = $qb->getQuery()->getResult();
            $return = '';
            $d = ','; // this is the default but i like to be explicit
            foreach ($members as $member) {
                foreach ($member->getBeneficiaries() as $beneficiary) {
                    $return .=
                        $beneficiary->getMemberNumber() . $d .
                        $beneficiary->getFirstname() . $d .
                        $beneficiary->getLastname() . $d .
                        $beneficiary->getEmail() . $d .
                        $beneficiary->getPhone() .
                        "\n";
                }
            }
            return new Response($return, 200, array(
                'Content-Encoding: UTF-8',
                'Content-Type' => 'application/force-download; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="emails_' . date('dmyhis') . '.csv"'
            ));
        } else if ($action === "mail") {
            return $this->redirectToRoute('mail_edit', [
                'request' => $request
            ], 307);
        } else if ($action === "swipe") {
            return $this->redirectToRoute('swipe_print', [
                'request' => $request
            ], 307);
        } else {
            $qb = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
            $members = new Paginator($qb->getQuery());
        }

        return $this->render('admin/user/list.html.twig', array(
            'members' => $members,
            'form' => $form->createView(),
            'nb_of_result' => $max,
            'page' => $page,
            'nb_of_pages' => $nb_of_pages
        ));
    }


    /**
     * Lists all users with ROLE_ADMIN.
     *
     * @param Request $request , SearchUserFormHelper $formHelper
     * @param SearchUserFormHelper $formHelper
     * @return Response
     * @Route("/admin_users", name="admins_list")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function adminUsersAction(Request $request, SearchUserFormHelper $formHelper)
    {
        $em = $this->getDoctrine()->getManager();

        $admins = $em->getRepository("AppBundle:User")->findByRole('ROLE_ADMIN');
        $delete_forms = array();
        foreach ($admins as $admin) {
            $delete_forms[$admin->getId()] = $this->createFormBuilder()
                ->setAction($this->generateUrl('user_delete', array('id' => $admin->getId())))
                ->setMethod('DELETE')
                ->getForm()->createView();
        }

        return $this->render('admin/user/admin_list.html.twig', array(
            'admins' => $admins,
            'delete_forms' => $delete_forms
        ));
    }

    /**
     * Registrations list
     *
     * @Route("/registrations", name="admin_registrations")
     * @Method({"POST","GET"})
     * @Security("has_role('ROLE_FINANCE_MANAGER')")
     */
    public function registrationsAction(Request $request)
    {
        $session = new Session();

        $qfrom = $request->query->get('from');
        if (!$qfrom) {
            $monday = strtotime('last monday', strtotime('tomorrow'));
            $from = new DateTime();
            $from->setTimestamp($monday);
        }else{
            $from = date_create_from_format('Y-m-d', $qfrom );
            if (!$from || $from->format('Y-m-d') != $qfrom) {
                $session->getFlashBag()->add('warning','la date "'.$qfrom.'"" n\'est pas au bon format (Y-m-d)');
                $monday = strtotime('last monday', strtotime('tomorrow'));
                $from = new DateTime();
                $from->setTimestamp($monday);
            }
        }
        $from = $from->setTime('0','0','0');

        $qto = $request->query->get('to');
        if ($qto) {
            $to = date_create_from_format('Y-m-d', $qto );
            if (!$to || $to->format('Y-m-d') != $qto) {
                $session->getFlashBag()->add('warning','la date "'.$qto.'"" n\'est pas au bon format (Y-m-d)');
                $to = null;
            }else{
                $to = $to->setTime('0','0','0');
            }
        }else{
            $to = null;
        }


        $em = $this->getDoctrine()->getManager();
        if (!($page = $request->get('page')))
            $page = 1;
        $limit = 25;
        $qb = $em->createQueryBuilder()->from('AppBundle\Entity\AbstractRegistration', 'r')
            ->select('count(r.id)')
            ->where('r.date >= :from')
            ->setParameter('from', $from);
        if ($to){
            $qb = $qb->andwhere('r.date <= :to')->setParameter('to', $to);
        }

        $max = $qb->getQuery()
            ->getSingleScalarResult();
        $nb_of_pages = intval($max / $limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        $repository = $em->getRepository('AppBundle:AbstractRegistration');
        $queryb = $repository->createQueryBuilder('r')
            ->where('r.date >= :from')
            ->setParameter('from', $from);
        if ($to){
            $queryb = $queryb->andwhere('r.date <= :to')->setParameter('to', $to);
        }
        $queryb = $queryb->orderBy('r.date', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $registrations = $queryb->getQuery()->getResult();
        $delete_forms = array();

        $table_name = $em->getClassMetadata('AppBundle:AbstractRegistration')->getTableName();
        $connection = $em->getConnection();
        $statement = $connection->prepare("SELECT date, SUM(sum_1) as sum_1,SUM(sum_2) as sum_2,SUM(sum_3) as sum_3,SUM(sum_4) as sum_4,SUM(sum_5) as sum_5,SUM(sum_6) as sum_6,SUM(grand_total) as grand_total FROM
(SELECT date_format(date,\"%Y-%m-%d\") as date,
SUM(IF(mode='1',amount,0)) as sum_1,
SUM(IF(mode='2',amount,0)) as sum_2,
SUM(IF(mode='3',amount,0)) as sum_3,
SUM(IF(mode='4',amount,0)) as sum_4,
SUM(IF(mode='5',amount,0)) as sum_5,
SUM(IF(mode='6',amount,0)) as sum_6,
SUM(amount) as grand_total
FROM ".$table_name."
WHERE date >= :from ".(($to) ? "AND date <= :to" : "")."
GROUP BY date) as sum GROUP BY date ORDER BY date DESC;");
        $statement->bindValue('from', $from->format('Y-m-d'));
        if ($to){
            $statement->bindValue('to', $to->format('Y-m-d'));
        }
        $statement->execute();
        $results = $statement->fetchAll();

        $totaux = array();
        foreach ($results as $result){
            $totaux[$result['date']] = $result;
        }

        $connection = $em->getConnection();
        $statement = $connection->prepare("SELECT
SUM(IF(mode='1',amount,0)) as sum_1,
SUM(IF(mode='2',amount,0)) as sum_2,
SUM(IF(mode='3',amount,0)) as sum_3,
SUM(IF(mode='4',amount,0)) as sum_4,
SUM(IF(mode='5',amount,0)) as sum_5,
SUM(IF(mode='6',amount,0)) as sum_6,
SUM(amount) as grand_total
FROM ".$table_name."
WHERE date >= :from ".(($to) ? "AND date <= :to" : "").";");
        $statement->bindValue('from', $from->format('Y-m-d'));
        if ($to){
            $statement->bindValue('to', $to->format('Y-m-d'));
        }
        $statement->execute();
        $grand_total = $statement->fetch();


        $re = '/1_([0-9]+)$/m';
        foreach ($registrations as $registration) {
            if ($registration->getType() == AbstractRegistration::TYPE_MEMBER){
                $matches = array();
                if (preg_match($re, $registration->getId(), $matches)) {
                    $delete_forms[$registration->getId()] = $this->getRegistrationDeleteForm($matches[1])->createView();
                }
            }
        }

        return $this->render('admin/registrations/list.html.twig',
            array(
                'R' => new Registration(),
                'registrations' => $registrations,
                'grand_total' => $grand_total,
                'totaux' => $totaux,
                'delete_forms' => $delete_forms,
                'page' => $page,
                'from' => $from,
                'to' => $to,
                'nb_of_pages' => $nb_of_pages));
    }

    /**
     * edit registration
     *
     * @Route("/registration/{id}/edit", name="admin_registration_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_FINANCE_MANAGER')")
     */
    public function editRegistrationAction(Request $request, Registration $registration)
    {
        $session = new Session();
        if ($registration->getId() && ($request->attributes->get('id') == $registration->getId())){
            $edit_form = $this->createForm(RegistrationType::class, $registration);
            $edit_form->handleRequest($request);
            if ($edit_form->isSubmitted() && $edit_form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($registration);
                $em->flush();
                $session->getFlashBag()->add('success', 'La ligne a bien été éditée !');
                return $this->redirectToRoute("admin_registrations");
            }
        }else{
            $session->getFlashBag()->add('error', 'l\'entrée #'.$request->attributes->get('id').' n\'a pas été trouvée');
            return $this->redirectToRoute("admin_registrations");
        }

        return $this->render('admin/registrations/edit.html.twig', array('edit_form' => $edit_form->createView(),'registration' => $registration));
    }

    /**
     * remove registration
     *
     * @Route("/registration/{id}/remove", name="admin_registration_remove")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeRegistrationAction(Request $request, Registration $registration)
    {
        $session = new Session();
        $form = $this->getRegistrationDeleteForm($registration->getId());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($registration->getUser() && count($registration->getUser()->getRegistrations()) === 1 && $registration === $registration->getUser()->getLastRegistration()) {
                $session->getFlashBag()->add('error', 'C\'est la seule adhésion de cette adhérent, corrigez là plutôt que de la supprimer');
            } else {
                $em = $this->getDoctrine()->getManager();
                if ($registration->getUser()) {
                    $registration->getUser()->removeRegistration($registration);
                    $em->persist($registration->getUser());
                }
                if ($registration->getRegistrar()) {
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
     * @param integer $registration_id
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getRegistrationDeleteForm(int $registration_id)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_registration_remove', array('id' => $registration_id)))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Helloasso payments list
     *
     * @Route("/helloassoPayments", name="helloasso_payments")
     * @Method("GET")
     * @Security("has_role('ROLE_FINANCE_MANAGER')")
     */
    public function helloassoPaymentsAction(Request $request)
    {
        if (!($page = $request->get('page')))
            $page = 1;
        $limit = 50;
        $max = $this->getDoctrine()->getManager()->createQueryBuilder()->from('AppBundle\Entity\HelloassoPayment', 'n')
            ->select('count(n.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $nb_of_pages = intval($max / $limit);
        if ($max > 0)
            $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        $payments = $this->getDoctrine()->getManager()
            ->getRepository('AppBundle:HelloassoPayment')
            ->findBy(array(), array('created_at' => 'DESC', 'date' => 'DESC'), $limit, ($page - 1) * $limit);
        $delete_forms = array();
        foreach ($payments as $payment) {
            $delete_forms[$payment->getId()] = $this->getPaymentDeleteForm($payment)->createView();
        }

        //todo: save this somewhere ?
        $campaigns_json = $this->container->get('AppBundle\Helper\Helloasso')->get('campaigns');
        $campaigns = array();
        foreach ($campaigns_json->resources as $c) {
            $campaigns[intval($c->id)] = $c;
        }

        return $this->render(
            'admin/helloasso/payments.html.twig',
            array('payments' => $payments,
                'campaigns' => $campaigns,
                'delete_forms' => $delete_forms,
                'page' => $page,
                'nb_of_pages' => $nb_of_pages));
    }

    /**
     * Helloasso browser
     *
     * @Route("/helloassoBrowser", name="helloasso_browser")
     * @Method("GET")
     * @Security("has_role('ROLE_FINANCE_MANAGER')")
     */
    public function helloassoBrowserAction(Request $request)
    {
        if (!($page = $request->get('page')))
            $page = 1;

        if (!($campaignId = $request->get('campaign'))) {
            $campaigns_json = $this->container->get('AppBundle\Helper\Helloasso')->get('campaigns');
            $campaigns = $campaigns_json->resources;
            return $this->render(
                'admin/helloasso/browser.html.twig',
                array('campaigns' => $campaigns));
        } else {
            $campaignId = str_pad($campaignId, 12, '0', STR_PAD_LEFT);
            $campaign_json = $this->container->get('AppBundle\Helper\Helloasso')->get('campaigns/' . $campaignId);
            $payments_json = $this->container->get('AppBundle\Helper\Helloasso')->get('campaigns/' . $campaignId . '/payments', array('page' => $page));
            $page = $payments_json->pagination->page;
            $nb_of_pages = $payments_json->pagination->max_page;
            $results_per_page = $payments_json->pagination->results_per_page;
            return $this->render(
                'admin/helloasso/browser.html.twig',
                array('payments' => $payments_json->resources,
                    'page' => $page,
                    'campaign' => $campaign_json,
                    'nb_of_pages' => $nb_of_pages));
        }

    }

    /**
     * Helloasso manual paiement add
     *
     * @Route("/helloassoManualPaimentAdd/", name="helloasso_manual_paiement_add")
     * @Method("POST")
     * @Security("has_role('ROLE_FINANCE_MANAGER')")
     */
    public function helloassoManualPaimentAddAction(Request $request)
    {
        $session = new Session();
        if (!($paiementId = $request->get('paiementId'))) {
            $session->getFlashBag()->add('error', 'missing paiment id');
            return $this->redirectToRoute('helloasso_browser');
        } else {
            $payment_json = $this->container->get('AppBundle\Helper\Helloasso')->get('payments/' . $paiementId);

            $em = $this->getDoctrine()->getManager();
            $exist = $em->getRepository('AppBundle:HelloassoPayment')->findOneBy(array('paymentId' => $payment_json->id));

            if ($exist) {
                $session->getFlashBag()->add('error', 'Ce paiement est déjà enregistré');
                return $this->redirectToRoute('helloasso_browser', array('campaign' => $exist->getCampaignId()));
            }

            $payments = array();
            $action_json = null;
            $dispatcher = $this->get('event_dispatcher');
            foreach ($payment_json->actions as $action) {
                $action_json = $this->container->get('AppBundle\Helper\Helloasso')->get('actions/' . $action->id);
                $payment = $em->getRepository('AppBundle:HelloassoPayment')->findOneBy(array('paymentId' => $payment_json->id));
                if ($payment) { //payment already exist (created from a previous actions in THIS loop)
                    $amount = $action_json->amount;
                    $amount = str_replace(',', '.', $amount);
                    $payment->setAmount($payment->getAmount() + $amount);
                } else {
                    $payment = new HelloassoPayment();
                    $payment->fromActionObj($action_json);
                }
                $em->persist($payment);
                $em->flush();
                $payments[$payment->getId()] = $payment;
            }
            foreach ($payments as $payment) {
                $dispatcher->dispatch(
                    HelloassoEvent::PAYMENT_AFTER_SAVE,
                    new HelloassoEvent($payment)
                );
            }

            $session->getFlashBag()->add('success', 'Ce paiement a bien été enregistré');
            return $this->redirectToRoute('helloasso_browser', array('campaign' => $action_json->id_campaign));
        }
    }

    /**
     * remove payment
     *
     * @Route("/helloasso/{id}", name="helloasso_payment_remove")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removePaymentAction(Request $request, HelloassoPayment $payment)
    {
        $session = new Session();
        $form = $this->getPaymentDeleteForm($payment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            if ($payment->getRegistration()) {
                $session->getFlashBag()->add('error', 'ce paiement est lié à une adhésion');
                return $this->redirectToRoute('helloasso_payments');
            }
            $em->remove($payment);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le paiement a bien été supprimé !');
        }
        return $this->redirectToRoute('helloasso_payments');
    }

    /**
     * @param HelloassoPayment $payment
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getPaymentDeleteForm(HelloassoPayment $payment)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('helloasso_payment_remove', array('id' => $payment->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Widget generator
     *
     * @Route("/widget", name="widget_generator")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function widgetBuilderAction(Request $request){
        $form = $this->createFormBuilder()
            ->add('job', EntityType::class, array(
                'label' => 'Poste',
                'class' => 'AppBundle:Job',
                'choice_label'=> 'name',
                'multiple'     => false,
                'required' => true
            ))
            ->add('display_end', CheckboxType::class, array('required' => false, 'label' => 'Afficher l\'heure de fin'))
            ->add('display_on_empty', CheckboxType::class, array('required' => false, 'label' => 'Afficher les créneaux vides'))
            ->add('generate', SubmitType::class, array('label' => 'generer'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {
            $data = $form->getData();
            return $this->render('admin/widget/generate.html.twig', array(
                'query_string' => 'job_id='.$data['job']->getId().'&display_end='.$data['display_end'].'&display_on_empty='.$data['display_on_empty'],
                'form' => $form->createView(),
            ));
        }

        return $this->render('admin/widget/generate.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Import from CSV
     *
     * @Route("/importcsv", name="user_import_csv")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function csvImportAction(Request $request, KernelInterface $kernel)
    {
        $form = $this->createFormBuilder()
            ->add('submitFile', FileType::class, array('label' => 'File to Submit'))
            ->add('delimiter', ChoiceType::class, array('label' => 'delimiter','choices'  => array(
                'virgule ,' => ',',
                'point virgule ;' => ';',)))
            //->add('persist', CheckboxType::class, array('required' => false, 'label' => 'Sauver en base'))
            //->add('compute', SubmitType::class, array('label' => 'Importer les données'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {

            // Get file
            $file = $form->get('submitFile');
            $delimiter = ($form->get('delimiter')) ? $form->get('delimiter')->getData() : ',';
            //$persist = ($form->get('persist')) ? $form->get('persist')->getData() : false;

            // Your csv file here when you hit submit button
            //$data = $file->getData();
            $filename = $file->getData()->getPathName();

            $application = new Application($kernel);
            $application->setAutoExit(false);

            $input = new ArrayInput([
                'command' => 'app:import:users',
                '--delimiter' => $delimiter,
                'file' => $filename,
                '--default_mapping' => true
            ]);

            // You can use NullOutput() if you don't need the output
            $output = new BufferedOutput();
            $application->run($input, $output);

            // return the output, don't use if you used NullOutput()
            $content = $output->fetch();

            $request->getSession()->getFlashBag()->add('notice', 'Le fichier a été traité.');

            return $this->render('admin/user/import_return.html.twig', array(
                'content' => $content,
            ));

        }

        return $this->render('admin/user/import.html.twig', array(
            'form' => $form->createView(),
        ));
    }
}
