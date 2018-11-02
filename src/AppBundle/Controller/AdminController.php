<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Commission;
use AppBundle\Entity\HelloassoPayment;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Formation;
use AppBundle\Entity\User;
use AppBundle\Event\HelloassoEvent;
use AppBundle\Form\BeneficiaryType;
use AppBundle\Form\UserType;
use AppBundle\Service\SearchUserFormHelper;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use OAuth2\OAuth2;
use Ornicar\GravatarBundle\GravatarApi;
use Ornicar\GravatarBundle\Templating\Helper\GravatarHelper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
        if (!($page = $request->get('page')))
            $page = 1;
        $limit = 25;
        $max = $this->getDoctrine()->getManager()->createQueryBuilder()->from('AppBundle\Entity\Registration', 'u')
            ->select('count(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $nb_of_pages = intval($max / $limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        $registrations = $this->getDoctrine()->getManager()
            ->getRepository('AppBundle:Registration')
            ->findBy(array(), array('created_at' => 'DESC', 'date' => 'DESC'), $limit, ($page - 1) * $limit);
        $delete_forms = array();
        $edit_forms = array();
        foreach ($registrations as $registration) {
            $delete_forms[$registration->getId()] = $this->getRegistrationDeleteForm($registration)->createView();
            $form = $this->get('form.factory')->createNamed('registration_edit_' . $registration->getId(), 'AppBundle\Form\RegistrationType', $registration);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($registration);
                $em->flush();
                $session->getFlashBag()->add('success', 'La ligne a bien été éditée !');
                //recreate the form with new data
                $form = $this->get('form.factory')->createNamed('registration_edit_' . $registration->getId(), 'AppBundle\Form\RegistrationType', $registration);
            }

            $edit_forms[$registration->getId()] = $form->createView();
        }
        return $this->render('admin/registrations/list.html.twig',
            array(
                'registrations' => $registrations,
                'delete_forms' => $delete_forms,
                'edit_forms' => $edit_forms,
                'page' => $page,
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
        $edit_form = $this->createForm('AppBundle\Form\RegistrationType', $registration);
        $edit_form->handleRequest($request);
        if ($edit_form->isSubmitted() && $edit_form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($registration);
            $em->flush();
            $session->getFlashBag()->add('success', 'La ligne a bien été éditée !');
            return $this->redirectToRoute("admin_registrations");
        }
        return $this->render('admin/registrations/edit.html.twig', array('edit_form' => $edit_form->createView()));

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
        $form = $this->getRegistrationDeleteForm($registration);
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
     * @param Registration $registration
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getRegistrationDeleteForm(Registration $registration)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_registration_remove', array('id' => $registration->getId())))
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
     * DEPRECATED
     * TODO: Mettre à jour avec un format simple
     */
    public function csvImportAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('submitFile', FileType::class, array('label' => 'File to Submit'))
            ->add('delimiter', TextType::class, array('label' => 'delimiter', 'attr' => array(
                'placeholder' => ',',
            ), 'data' => ','))
            ->add('persist', CheckboxType::class, array('required' => false, 'label' => 'Sauver en base'))
            ->add('compute', SubmitType::class, array('label' => 'compute'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {

            // Get file
            $file = $form->get('submitFile');
            $delimiter = ($form->get('delimiter')) ? $form->get('delimiter')->getData() : ',';
            $persist = ($form->get('persist')) ? $form->get('persist')->getData() : false;

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
                    if (count($data) > 11 && isset($data[3]) && isset($data[4]) && count($matches) && strlen($data[3]) > 1 && strlen($data[4]) > 1) { // on ne traite que les colonnes qui commence par un numéro d'adhérent valide (entier)
                        $member_number = $data[0];
                        $user = $em->getRepository('AppBundle:User')->findOneBy(array("member_number" => $member_number));
                        if ($user) {
                            $mail = $data[9];
                            if (isset($data[9]) && filter_var($mail, FILTER_VALIDATE_EMAIL) && ($user->getEmail() != $mail)) {
                                $user_exist = $em->getRepository('AppBundle:User')->findOneBy(array("email" => $mail));
                                if (!$user_exist) {
                                    $user->setEmail($mail);
                                    if ($persist)
                                        $em->persist($user);
                                    $return[] = array($user, array("error", "user with same member number already exist, email updated"));
                                } else {
                                    $return[] = array($user, array("error", "user with same member number already exist, email change but already in use"));
                                }
                            } else {
                                $return[] = array($user, array("error", "user with same member number already exist"));
                            }
                        } else {
                            $mail = $data[9];
                            $validator = $this->container->get('validator');
                            $constraints = array(
                                new EmailConstraint(),
                                new NotBlank()
                            );
                            $error = $validator->validate($mail, $constraints);
                            if ($error->count()) {
                                $return[] = array($user, array("error", "email is not valid (" . $mail . ")"));
                            } else {
                                $user = $em->getRepository('AppBundle:User')->findOneBy(array("email" => $mail));
                                $already_registred = (isset($emails[$mail])) ? true : false;
                                if ($user || $already_registred)
                                    $return[] = array($user, array("error", "user with same email already exist"));
                                else {
                                    $user = new User();
                                    $firstname = trim(preg_replace('/\s\s+/', ' ', $data[4]));
                                    $lastname = trim(preg_replace('/\s\s+/', ' ', $data[3]));
                                    $username = User::makeUsername($firstname, $lastname);
                                    $qb = $em->createQueryBuilder();
                                    $users = $qb->select('u')->from('AppBundle\Entity\User', 'u')
                                        ->where($qb->expr()->like('u.username', $qb->expr()->literal($username . '%')))
                                        ->getQuery()
                                        ->getResult();
                                    //$users = $em->getRepository('AppBundle:User')->findBy(array("username"=>$username));
                                    $already_registred = (isset($usernames[$username])) ? $usernames[$username] : 0;
                                    if (count($users) || $already_registred) {
                                        $username = User::makeUsername($firstname, $lastname, count($users) + 1 + $already_registred);
                                    }
                                    if (strlen($username) > 3) {
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
                                        //$beneficiary->setAmbassador(($data[8]!='')&&$data[8]=='1');
                                        //$beneficiary->setExpert(false);//default all false
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
                                        if (!$reglement && strtolower($data[2]) == 'site')
                                            $reglement = 'cb';
                                        switch ($reglement) {
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
                                        $return[] = array($user, array("check", "user added"));
                                        $usernames[$user->getUsername()] = (isset($usernames[$user->getUsername()])) ? $usernames[$user->getUsername()] + 1 : 1;
                                        $emails[$user->getEmail()] = true;
                                        if ($persist)
                                            $em->persist($user);
                                    } else {
                                        $return[] = array($user, array("error", "username build to short"));
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

            if ($persist) {
                $request->getSession()->getFlashBag()->add('notice', 'Le fichier a été traité complétement.');
                return $this->redirectToRoute('user_index');
            } else {
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
