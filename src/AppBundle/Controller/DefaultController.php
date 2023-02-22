<?php

namespace AppBundle\Controller;

use AppBundle\Entity\BookedShift;
use AppBundle\Entity\Code;
use AppBundle\Entity\HelloassoPayment;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Entity\User;
use AppBundle\Event\HelloassoEvent;
use AppBundle\Form\AutocompleteBeneficiaryCollectionType;
use AppBundle\Service\MembershipService;
use AppBundle\Twig\Extension\AppExtension;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Serializer\Encoder\JsonDecode;

class DefaultController extends Controller
{

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $first = null;
        $em = $this->getDoctrine()->getManager();
        $securityContext = $this->container->get('security.authorization_checker');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $session = new Session();
            $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

            if ($current_app_user->getBeneficiary() != null) { //member only

                /** @var Membership $membership */
                $membership = $current_app_user->getBeneficiary()->getMembership();

                if ($membership->getWithdrawn()) {
                    $this->container->get('security.token_storage')->setToken(null);
                    $this->container->get('session')->invalidate();
                    $session->getFlashBag()->add('error', 'Compte fermé !');
                    return $this->redirectToRoute('homepage');
                }

                $cycle_end = $this->get('membership_service')->getEndOfCycle($membership);
                $dayAfterEndOfCycle = clone $cycle_end;
                $dayAfterEndOfCycle->modify('+1 day');
                $profileUrlHtml = "<a style=\"text-decoration:underline;color:white;\" href=\"" . $this->get('router')->generate('fos_user_profile_show') . "\"><i class=\"material-icons tiny\">settings</i> ton profil</a>.";
                if ($membership->getFrozenChange() && !$membership->getFrozen()) {
                    $now = new \DateTime('now');
                    $session->getFlashBag()->add('warning',
                        'Comme demandé, ton compte sera gelé dans ' .
                        date_diff($now, $cycle_end)->format('%a jours') .
                        ', le <strong>' . $this->container->get('twig')->getExtension(AppExtension::class)->date_fr_long($dayAfterEndOfCycle) . '</strong>.' .
                        "<br />Pour annuler, visite " . $profileUrlHtml);
                }
                if ($membership->getFrozenChange() && $membership->getFrozen()) {
                    $now = new \DateTime('now');
                    $session->getFlashBag()->add('notice',
                        'Comme demandé, ton compte sera dégelé dans ' .
                        date_diff($now, $cycle_end)->format('%a jours') .
                        ', le <strong>' . $this->container->get('twig')->getExtension(AppExtension::class)->date_fr_long($dayAfterEndOfCycle) . '</strong>.' .
                        "<br />Pour annuler, visite " . $profileUrlHtml);
                }

                if ($this->get('membership_service')->canRegister($membership)) {
                    if ($membership->getRegistrations()->count() <= 0) {
                        $session->getFlashBag()->add('warning', 'Pour poursuivre entre ton adhésion en ligne !');
                    }else{
                        $remainder = $this->get('membership_service')->getRemainder($membership);
                        $remainingDays = intval($remainder->format("%R%a"));
                        if ($remainingDays < 0)
                            $session->getFlashBag()->add('error', 'Oups, ton adhésion a expiré il y a ' . $remainder->format('%a jours') . '... n\'oublie pas de ré-adhérer !');
                        else {
                            $session->getFlashBag()->add('warning',
                                'Ton adhésion expire dans ' . $remainingDays . ' jours.<br>' .
                                'Tu peux ré-adhérer en ligne par carte bancaire ou bien au bureau des membres par chèque, espèce ou ' .
                                $this->getParameter('local_currency_name') .
                                '.');
                        }
                    }
                } elseif ($membership->getRegistrations()->count() <= 0) {
                    $session->getFlashBag()->add('error', 'Aucune adhésion enregistrée !');
                }
            }
        } else {
            return $this->render('default/index_anon.html.twig', [
                'bucketsByDay' => $this->getSchedule(),
                'hours' => $this->getHours()
            ]);
        }
        $qb = $em->createQueryBuilder();
        $futur_events = $qb->select('e')->from('AppBundle\Entity\Event', 'e')
            ->Where("e.date > :now")
            ->orderBy("e.id", 'ASC')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        $dynamicContent = $em->getRepository('AppBundle:DynamicContent')->findOneByCode("HOME")->getContent();

        return $this->render('default/index.html.twig', [
            'events' => $futur_events,
            'dynamicContent' => $dynamicContent
        ]);
    }

    /**
     * @Route("/schedule", name="schedule", methods={"GET","POST"})
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED', user)")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function scheduleAction()
    {
        return $this->render('booking/schedule.html.twig', [
            'bucketsByDay' => $this->getSchedule(),
            'hours' => $this->getHours()
        ]);
    }

    private function getHours() {
        $hours = array();
        for ($i = 6; $i < 22; $i++) { //todo put this in conf
            $hours[] = $i;
        }
        return $hours;
    }

    private function getSchedule() {
        $em = $this->getDoctrine()->getManager();
        $today = strtotime('today');
        $from = new \DateTime();
        $from->setTimestamp($today);
        $to = new \DateTime();
        $to->modify('+7 days');
        $shifts = $em->getRepository('AppBundle:Shift')->findFrom($from, $to);
        $bucketsByDay = array();
        foreach ($shifts as $shift) {
            $day = $shift->getStart()->format("d m Y");
            $job = $shift->getJob()->getId();
            $interval = $shift->getIntervalCode();
            if (!isset($bucketsByDay[$day])) {
                $bucketsByDay[$day] = array();
            }
            if (!isset($bucketsByDay[$day][$job])) {
                $bucketsByDay[$day][$job] = array();
            }
            if (!isset($bucketsByDay[$day][$job][$interval])) {
                $bucket = new ShiftBucket();
                $bucketsByDay[$day][$job][$interval] = $bucket;
            }
            $bucketsByDay[$day][$job][$interval]->addShift($shift);
        }
        return $bucketsByDay;
    }

    /**
     * @Route("/cardReader", name="cardReader")
     */
    public function cardReaderAction(Request $request)
    {
        $this->denyAccessUnlessGranted('card_reader', $this->getUser());
        $em = $this->getDoctrine()->getManager();

        // in progress shifts
        $shifts_in_progress = $em->getRepository('AppBundle:Shift')->findInProgress();
        $buckets_in_progress = $this->get('shift_service')->generateShiftBuckets($shifts_in_progress);
        // upcoming shifts
        $shifts_upcoming = $em->getRepository('AppBundle:Shift')->findUpcomingToday();
        $buckets_upcoming = $this->get('shift_service')->generateShiftBuckets($shifts_upcoming);

        $dynamicContent = $em->getRepository('AppBundle:DynamicContent')->findOneByCode('CARD_READER')->getContent();

        return $this->render('default/card_reader/index.html.twig', [
            "buckets_in_progress" => $buckets_in_progress,
            "buckets_upcoming" => $buckets_upcoming,
            "dynamicContent" => $dynamicContent
        ]);
    }

    /**
     * @Route("/helloassoNotify", name="helloasso_notify", methods={"POST"})
     * inspiré de
     * https://github.com/Breizhicoop/HelloDoli/blob/master/adhesion.php
     * https://github.com/Mailforgood/HelloAsso.Api.Doc/blob/master/HelloAsso.Api.Samples/php/helloasso_stat.php
     */
    public function helloassoNotify(Request $request)
    {

        $logger = $this->get('logger');
        $logger->info('helloasso notify', $_POST);

        $actionId = $_POST['action_id'];

        if (!$actionId) { //missing notification id
            $logger->critical("missing action id");
            return $this->json(array('success' => false, "message" => "missing action id in POST content"));
        }

        $actionId = str_pad($actionId, 12, '0', STR_PAD_LEFT);

        $action_json = $this->container->get('AppBundle\Helper\Helloasso')->get('actions/' . $actionId);

        if (!isset($action_json->id)) {
            $message = 'Unable to find an action for action id ' . $actionId;
            if (isset($action_json->code)) {
                $logger->critical($message . ' code ' . $action_json->code);
                return $this->json(array('success' => false, "code" => $action_json->code, "message" => $action_json->message));
            } else {
                $logger->critical($message);
                return $this->json(array('success' => false, "message" => "wrong api response for actions/" . $actionId));
            }
        }
        $payment_json = $this->container->get('AppBundle\Helper\Helloasso')->get('payments/' . $action_json->id_payment);
        if (!isset($payment_json->id)) {
            $message = 'Unable to find a payment for payment id ' . $action_json->id_payment;
            if (isset($payment_json->code)) {
                $logger->critical($message . ' code ' . $payment_json->code);
                return $this->json(array('success' => false, "code" => $payment_json->code, "message" => $payment_json->message));
            } else {
                $logger->critical($message);
                return $this->json(array('success' => false, "message" => "wrong api response for payments/" . $action_json->id_payment));
            }
        }

        $em = $this->getDoctrine()->getManager();
        $exist = $em->getRepository('AppBundle:HelloassoPayment')->findOneBy(array('paymentId' => $payment_json->id));

        if ($exist) { //notification already exist
            $logger->info("notification already exist");
            return $this->json(array('success' => false, "message" => "notification already exist"));
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

        return $this->json(array('success' => true));

    }

    /**
     * @Route("/shift/{id}/contact_form", name="shift_contact_form", methods={"GET","POST"})
     */
    public function shiftContactFormAction(Shift $shift, Request $request, \Swift_Mailer $mailer)
    {

        $em = $this->getDoctrine()->getManager();
        $coShifters = $em->getRepository('AppBundle:Beneficiary')->findCoShifters($shift);
        $formBuilder = $this->createFormBuilder();
        $formBuilder->add('from', HiddenType::class, array('data' => $shift->getShifter()->getId()));
        $formBuilder->add('to', AutocompleteBeneficiaryCollectionType::class, [
            'label' => 'A',
            'data' => $coShifters,
        ]);
        $formBuilder->add('message', TextareaType::class, [
            'attr' => ['class' => 'materialize-textarea'],
            'label' => 'Message',
            'data' => 'Bonjour XX,'.PHP_EOL."Tu n'es toujours pas arrivé pour notre créneau.".PHP_EOL."Est-ce que tout va bien ?".PHP_EOL."A très vite,".PHP_EOL.$shift->getShifter()->getFirstName().PHP_EOL.PHP_EOL."Bonjour à tou.te.s,".PHP_EOL."Je vais en être en retard pour mon créneau.".PHP_EOL."Je serai à l'épicerie d'ici XX minutes.".PHP_EOL."A tout de suite,".PHP_EOL.$shift->getShifter()->getFirstName()
        ]);
        $formBuilder->setAction($this->generateUrl('shift_contact_form', array('id' => $shift->getId())));
        $formBuilder->setMethod('POST');
        $form = $formBuilder->getForm();

        if ($form->handleRequest($request)->isValid()) {
            $beneficiaries = $form->get('to')->getData();
            $from = $form->get('from')->getData();
            $from = $em->getRepository('AppBundle:Beneficiary')->findOneBy(array('id' => $from));
            $emails = array();
            $firstnames = array();
            foreach ($beneficiaries as $beneficiary) {
                $emails[] = $beneficiary->getEmail();
                $firstnames[] = $beneficiary->getFirstname();
            }
            $message = (new \Swift_Message('[ESPACE MEMBRES] Un message de ' . $from->getFirstName() . " " . substr($from->getLastName(),0,1)))
                ->setFrom($this->getParameter('transactional_mailer_user'))
                ->setReplyTo($from->getEmail())
                ->setBcc($emails)
                ->setBody(
                    $this->renderView(
                        'emails/coshifter_message.html.twig',
                        array(
                            'message' => trim($form->get('message')->getData()),
                            'from' => $from,
                            'firstnames' => $firstnames,
                            'shift' => $shift)
                    ),
                    'text/html'
                );
            $mailer->send($message);
            $session = new Session();
            if (count($firstnames) > 1) {
                $last_firstname = array_pop($firstnames);
                $firstnames = implode(', ', $firstnames);
                $firstnames .= ' et ' . $last_firstname;
            } else {
                $firstnames = $firstnames[0];
            }

            $session->getFlashBag()->add('success', 'Ton message a été transmis à ' . $firstnames);
            return $this->redirectToRoute('homepage');
        }
        return $this->render('booking/_partial/home_shift_contactform.html.twig', array(
            'shift' => $shift,
            'form' => $form->createView()
        ));
    }

    /**
     * @Route("/widget", name="widget", methods={"GET"})
     */
    public function widgetAction(Request $request)
    {
        $job_id = $request->get('job_id');
        $buckets = array();
        $display_end = $request->query->has('display_end') ? ($request->get('display_end') == 1) : false;
        $display_on_empty = $request->query->has('display_on_empty') ? ($request->get('display_on_empty') == 1) : false;
        $title = $request->query->has('title') ? ($request->get('title') == 1) : true;
        $job = null;
        if ($job_id) {
            $em = $this->getDoctrine()->getManager();
            $job = $em->getRepository('AppBundle:Job')->find($job_id);
            if ($job) {
                $shifts = $em->getRepository('AppBundle:Shift')->findFuturesWithJob($job);
                foreach ($shifts as $shift) {
                    $day = $shift->getStart()->format("d m Y");
                    $interval = $shift->getIntervalCode();
                    if (!isset($buckets[$interval . $day])) {
                        $buckets[$interval . $day] = new ShiftBucket();
                    }
                    $buckets[$interval . $day]->addShift($shift);
                }
            }
        }

        return $this->render('default/widget.html.twig', [
            'job' => $job,
            'buckets' => $buckets,
            'display_end' => $display_end,
            'display_on_empty' => $display_on_empty,
            'title' => $title
        ]);

    }
}
