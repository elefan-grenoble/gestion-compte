<?php

namespace App\Controller;

use App\Entity\BookedShift;
use App\Entity\Code;
use App\Entity\HelloassoPayment;
use App\Entity\Membership;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Entity\ShiftBucket;
use App\Entity\SwipeCard;
use App\Entity\User;
use App\Event\HelloassoEvent;
use App\Event\SwipeCardEvent;
use App\Helper\Helloasso;
use App\Service\MembershipService;
use App\Service\ShiftService;
use App\Twig\Extension\AppExtension;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(
        Request $request,
        EntityManagerInterface $em,
        AuthorizationCheckerInterface $authorizationChecker,
        UrlGeneratorInterface $urlGenerator,
        MembershipService $membershipService,
        $localCurrencyName,
        $kernelProjectDir,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session
    ) {
        $first = null;
        if ($authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $session = new Session();
            $current_app_user = $this->getUser();

            if ($current_app_user->getBeneficiary() != null) { //member only

                /** @var Membership $membership */
                $membership = $current_app_user->getBeneficiary()->getMembership();

                if ($membership->getWithdrawn()) {
                    $tokenStorage->setToken(null);
                    $session->invalidate();
                    $session->getFlashBag()->add('error', 'Compte fermé !');
                    return $this->redirectToRoute('homepage');
                }

                $dayAfterEndOfCycle = clone $membership->endOfCycle();
                $dayAfterEndOfCycle->modify('+1 day');
                if ($membership->getFrozenChange() && !$membership->getFrozen()) {
                    $now = new \DateTime('now');
                    $session->getFlashBag()->add('warning',
                        'Comme demandé, ton compte sera gelé dans ' .
                        date_diff($now, $membership->endOfCycle())->format('%a jours') .
                        ', le <strong>' . AppExtension::date_fr_long($dayAfterEndOfCycle) . '</strong>' .
                        " Pour annuler, visite <a style=\"text-decoration:underline;color:white;\" href=\"" .
                        $urlGenerator->generate('fos_user_profile_show')
                        . "\">ton profil <i class=\"material-icons tiny\">settings</i></a>");
                }
                if ($membership->getFrozenChange() && $membership->getFrozen()) {
                    $now = new \DateTime('now');
                    $session->getFlashBag()->add('notice',
                        'Comme demandé, ton compte sera dégelé dans ' .
                        date_diff($now, $membership->endOfCycle())->format('%a jours') .
                        ', le <strong>' . AppExtension::date_fr_long($dayAfterEndOfCycle) . '</strong>' .
                        " Pour annuler, visite <a style=\"text-decoration:underline;color:white;\" href=\"" .
                        $urlGenerator->generate('fos_user_profile_show')
                        . "\">ton profil <i class=\"material-icons tiny\">settings</i></a>");
                }

                if ($membershipService->canRegister($membership)) {
                    if ($membership->getRegistrations()->count() <= 0) {
                        $session->getFlashBag()->add('warning', 'Pour poursuivre entre ton adhésion en ligne !');
                    }else{
                        $remainder = $membershipService->getRemainder($membership);
                        $remainingDays = intval($remainder->format("%R%a"));
                        if ($remainingDays < 0)
                            $session->getFlashBag()->add('error', 'Oups, ton adhésion  a expiré il y a ' . $remainder->format('%a jours') . '... n\'oublie pas de ré-adhérer !');
                        else {
                            $session->getFlashBag()->add('warning',
                                'Ton adhésion expire dans ' . $remainingDays . ' jours.<br>' .
                                'Tu peux réadhérer en ligne par carte bancaire ou bien au bureau des membres par chèque, espèce ou ' .
                                $localCurrencyName .
                                '.');
                        }
                    }
                } elseif ($membership->getRegistrations()->count() <= 0) {
                    $session->getFlashBag()->add('error', 'Aucune adhésion enregistrée !');
                }
            }
        } else {
            return $this->render('default/index.html.twig', [
                'bucketsByDay' => $this->getSchedule($em),
                'hours' => $this->getHours()
            ]);
        }
        $qb = $em->createQueryBuilder();
        $futur_events = $qb->select('e')->from('App\Entity\Event', 'e')
            ->Where("e.date > :now")
            ->orderBy("e.id", 'ASC')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        $dynamicContent = $em->getRepository('App:DynamicContent')->findOneByCode("HOME")->getContent();

        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($kernelProjectDir) . DIRECTORY_SEPARATOR,
            'events' => $futur_events,
            'dynamicContent' => $dynamicContent
        ]);
    }

    /**
     * @Route("/schedule", name="schedule")
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED', user)")
     * @Method({"GET","POST"})
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function scheduleAction(EntityManagerInterface $em)
    {
        return $this->render('booking/schedule.html.twig', [
            'bucketsByDay' => $this->getSchedule($em),
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

    private function getSchedule(EntityManagerInterface $em)
    {
        $today = strtotime('today');
        $from = new \DateTime();
        $from->setTimestamp($today);
        $to = new \DateTime();
        $to->modify('+7 days');
        $shifts = $em->getRepository('App:Shift')->findFrom($from, $to);
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
    public function cardReaderAction(Request $request, EntityManagerInterface $em, ShiftService $shiftService)
    {
        $this->denyAccessUnlessGranted('card_reader', $this->getUser());
        $em = $this->getDoctrine()->getManager();
        $shifts = $em->getRepository('App:Shift')->findRemainingToday();
        $buckets = $shiftService->generateShiftBuckets($shifts);
        $buckets = $shiftService->removeEmptyShift($buckets);

        $dynamicContent = $em->getRepository('App:DynamicContent')->findOneByCode('CARD_READER')->getContent();

        return $this->render('default/card_reader.html.twig', [
            "buckets" => $buckets,
            "dynamicContent" => $dynamicContent
        ]);
    }

    /**
     * @Route("/check", name="check")
     * @Method({"POST","GET"})
     */
    public function checkAction(Request $request, EntityManagerInterface $em, EventDispatcherInterface $dispatcher, $swipeCardLogging)
    {
        $session = new Session();
        $code = $request->get('swipe_code');
        if (!$code) {
            return $this->redirectToRoute('cardReader');
        }
        if (!SwipeCard::checkEAN13($code)) {
            return $this->redirectToRoute('cardReader');
        }
        $code = substr($code, 0, -1); //remove controle
        $card = $em->getRepository('App:SwipeCard')->findOneBy(array('code' => $code, 'enable' => 1));
        if (!$card) {
            $session->getFlashBag()->add("error", "Oups, ce badge n'est pas actif ou n'existe pas");
        } else {
            $beneficiary = $card->getBeneficiary();
            $counter = $beneficiary->getMembership()->getTimeCount($beneficiary->getMembership()->endOfCycle(0));
            if ($swipeCardLogging) {
                $dispatcher = $this->get('event_dispatcher');
                $dispatcher->dispatch(SwipeCardEvent::SWIPE_CARD_SCANNED, new SwipeCardEvent($counter));
            }
            return $this->render('user/check.html.twig', [
                'beneficiary' => $beneficiary,
                'counter' => $counter
            ]);
        }

        return $this->redirectToRoute('cardReader');
    }

    /**
     * @Route("/helloassoNotify", name="helloasso_notify")
     * @Method({"POST"})
     * inspiré de
     * https://github.com/Breizhicoop/HelloDoli/blob/master/adhesion.php
     * https://github.com/Mailforgood/HelloAsso.Api.Doc/blob/master/HelloAsso.Api.Samples/php/helloasso_stat.php
     */
    public function helloassoNotify(Request $request, LoggerInterface $logger, EntityManagerInterface $em, EventDispatcherInterface $dispatcher, Helloasso $helloasso)
    {
        $logger->info('helloasso notify', $_POST);

        $actionId = $_POST['action_id'];

        if (!$actionId) { //missing notification id
            $logger->critical("missing action id");
            return $this->json(array('success' => false, "message" => "missing action id in POST content"));
        }

        $actionId = str_pad($actionId, 12, '0', STR_PAD_LEFT);

        $action_json = $helloasso->get('actions/' . $actionId);

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
        $payment_json = $helloasso->get('payments/' . $action_json->id_payment);
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

        $exist = $em->getRepository('App:HelloassoPayment')->findOneBy(array('paymentId' => $payment_json->id));

        if ($exist) { //notification already exist
            $logger->info("notification already exist");
            return $this->json(array('success' => false, "message" => "notification already exist"));
        }

        $payments = array();
        $action_json = null;
        foreach ($payment_json->actions as $action) {
            $action_json = $helloasso->get('actions/' . $action->id);
            $payment = $em->getRepository('App:HelloassoPayment')->findOneBy(array('paymentId' => $payment_json->id));
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
     * @Route("/shift/{id}/contact_form", name="shift_contact_form")
     * @Method({"GET","POST"})
     */
    public function shiftContactFormAction(Shift $shift, Request $request, \Swift_Mailer $mailer, EntityManagerInterface $em, $transactionalMailerUser)
    {

        $formBuilder = $this->createFormBuilder();
        $formBuilder->add('from', HiddenType::class, array('data' => $shift->getShifter()->getId()));
        $formBuilder->add('to', HiddenType::class);
        $formBuilder->add('message', TextareaType::class, array('attr' => array('class' => 'materialize-textarea', 'label' => 'message')));
        $formBuilder->setAction($this->generateUrl('shift_contact_form', array('id' => $shift->getId())));
        $formBuilder->setMethod('POST');
        $form = $formBuilder->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $to = $form->get('to')->getData();
            $to = json_decode($to);
            $from = $form->get('from')->getData();
            $beneficiaries = $em->getRepository('App:Beneficiary')->findBy(array('id' => $to));
            $from = $em->getRepository('App:Beneficiary')->findOneBy(array('id' => $from));
            $emails = array();
            $firstnames = array();
            foreach ($beneficiaries as $beneficiary) {
                $emails[] = $beneficiary->getEmail();
                $firstnames[] = $beneficiary->getFirstname();
            }
            $message = (new \Swift_Message('[ESPACE MEMBRES] Un message de ' . $from->getFirstName()))
                ->setFrom($transactionalMailerUser)
                ->setReplyTo($transactionalMailerUser)
                ->setBcc($emails)
                ->setBody(
                    $this->renderView(
                        'emails/coshifter_message.html.twig',
                        array(
                            'message' => trim($form->get('message')->getData()),
                            'from' => $from,
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

            $session->getFlashBag()->add('success', 'Ton message a été transmit à ' . $firstnames);
            return $this->redirectToRoute('homepage');
        } else {
            $shifts = $em->getRepository('App:Shift')->findBy(array('start' => $shift->getStart(), 'end' => $shift->getEnd()));
            $coShifts = array();
            foreach ($shifts as $s) {
                if ($s->getBooker() != null && $s->getId() != $shift->getId()) {
                    $coShifts[] = $s;
                }
            }
            return $this->render('booking/_partial/home_shift_contactform.html.twig', array(
                'shift' => $shift,
                'coShifts' => $coShifts,
                'form' => $form->createView()
            ));
        }
    }

    /**
     * @Route("/widget", name="widget")
     * @Method({"GET"})
     */
    public function widgetAction(Request $request, EntityManagerInterface $em)
    {
        $job_id = $request->get('job_id');
        $buckets = array();
        $display_end = $request->get('display_end') ? ($request->get('display_end') == 1) : false;
        $display_on_empty = $request->get('display_on_empty') ? ($request->get('display_on_empty') == 1) : false;
        $job = null;
        if ($job_id) {
            $job = $em->getRepository('App:Job')->find($job_id);
            if ($job) {
                $shifts = $em->getRepository('App:Shift')->findFuturesWithJob($job);
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
            'display_on_empty' => $display_on_empty
        ]);

    }
}
