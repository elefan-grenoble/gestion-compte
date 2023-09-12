<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Code;
use AppBundle\Entity\HelloassoPayment;
use AppBundle\Entity\Membership;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Entity\User;
use AppBundle\Event\HelloassoEvent;
use AppBundle\Twig\Extension\AppExtension;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;

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
            $to = new \DateTime();
            $to->modify('+7 days');
            $shifts = $em->getRepository('AppBundle:Shift')->findFutures($to);
            $bucketsByDay = $this->get('shift_service')->generateShiftBucketsByDayAndJob($shifts);

            return $this->render('default/index_anon.html.twig', [
                'bucketsByDay' => $bucketsByDay,
                'hours' => $this->getHours()
            ]);
        }

        $eventsFutureOrOngoing = $em->getRepository('AppBundle:Event')->findFutureOrOngoing();
        $dynamicContentTop = $em->getRepository('AppBundle:DynamicContent')->findOneByCode("HOME_TOP")->getContent();
        $dynamicContentBottom = $em->getRepository('AppBundle:DynamicContent')->findOneByCode("HOME_BOTTOM")->getContent();

        return $this->render('default/index.html.twig', [
            'eventsFutureOrOngoing' => $eventsFutureOrOngoing,
            'dynamicContentTop' => $dynamicContentTop,
            'dynamicContentBottom' => $dynamicContentBottom,
        ]);
    }

    public function footerAction()
    {
        $em = $this->getDoctrine()->getManager();

        $socialNetworks = $em->getRepository('AppBundle:SocialNetwork')->findAllDisplayedFooter();

        return $this->render('_partial/footer.html.twig', [
            'socialNetworks' => $socialNetworks,
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
        $em = $this->getDoctrine()->getManager();

        $shifts = $em->getRepository('AppBundle:Shift')->findFutures();
        $bucketsByDay = $this->get('shift_service')->generateShiftBucketsByDayAndJob($shifts);

        return $this->render('booking/schedule.html.twig', [
            'bucketsByDay' => $bucketsByDay,
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
}
