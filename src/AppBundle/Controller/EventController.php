<?php

namespace AppBundle\Controller;

use DateTime;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Event;
use AppBundle\Entity\Proxy;
use AppBundle\Form\ProxyType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\Session;


/**
 * Event controller.
 *
 * @Route("events")
 */
class EventController extends Controller
{
    /**
     * Event widget display
     * 
     * @Route("/widget", name="event_widget", methods={"GET"})
     */
    public function widgetAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $buckets = array();
        $eventKind = null;
        $eventDateMax = null;

        $filter_title = $request->query->has('title') ? ($request->get('title') == 1) : false;
        $filter_links = $request->query->has('links') ? ($request->get('links') == 1) : false;
        $filter_date_max = $request->query->has('date_max') ? ($request->get('date_max') ? new DateTime($request->get('date_max')) : null) : null;
        if ($filter_date_max) {
            $eventDateMax = clone($filter_date_max);
            $eventDateMax->modify('+1 day');  // also return events happening on max date
        }
        $filter_limit = $request->query->has('limit') ? ($request->get('limit') ? $request->get('limit') : null) : null;

        $filter_event_kind_id = $request->get('event_kind_id');
        if ($filter_event_kind_id) {
            $eventKind = $em->getRepository('AppBundle:EventKind')->find($filter_event_kind_id);
        }

        $events = $em->getRepository('AppBundle:Event')->findFutureOrOngoing($eventKind, $eventDateMax, $filter_limit);

        return $this->render('event/_partial/widget.html.twig', [
            'events' => $events,
            'eventKind' => $eventKind,
            'title' => $filter_title,
            'links' => $filter_links,
            'maxDate' => $filter_date_max,
        ]);
    }

    /**
     * Event home
     *
     * @Route("/", name="event_index", methods={"GET"})
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $eventsFuture = $em->getRepository('AppBundle:Event')->findFutures();
        $eventsOngoing = $em->getRepository('AppBundle:Event')->findOngoing();
        $eventsPast = $em->getRepository('AppBundle:Event')->findPast();

        return $this->render('event/index.html.twig', array(
            'eventsFuture' => $eventsFuture,
            'eventsOngoing' => $eventsOngoing,
            'eventsPast' => $eventsPast,
        ));
    }

    /**
     * Event detail
     *
     * @Route("/{id}", name="event_detail", methods={"GET"})
     */
    public function detailAction(Request $request, Event $event)
    {
        $em = $this->getDoctrine()->getManager();

        return $this->render('event/detail.html.twig', array(
            'event' => $event,
        ));
    }

    /**
     * Proxy new
     *
     * @Route("/{id}/proxy/give", name="event_proxy_give", methods={"GET","POST"})
     */
    public function giveProxyAction(Event $event, Request $request, \Swift_Mailer $mailer)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $max_event_proxy_per_member = $this->container->getParameter("max_event_proxy_per_member");

        // check if member hasn't already given a proxy
        $member_given_proxy = $em->getRepository('AppBundle:Proxy')->findOneBy(array("event" => $event, "giver" => $current_app_user->getBeneficiary()->getMembership()));
        if ($member_given_proxy) {
            $session->getFlashBag()->add('error', 'Oups, tu as déjà donné une procuration');
            return $this->redirectToRoute('homepage');
        }

        // check if member hasn't already received a proxy
        $membership = $current_app_user->getBeneficiary()->getMembership();
        $beneficiaries = $membership->getBeneficiaries();
        $beneficiariesId = array_map(function(Beneficiary $beneficiary) {
            return $beneficiary->getId();
        }, $beneficiaries->toArray());
        $member_received_proxies = $em->getRepository('AppBundle:Proxy')->findBy(
            array(
                "owner" => $beneficiariesId,
                "event" => $event
            )
        );
        if ($member_received_proxies) {
            foreach ($member_received_proxies as $rp) {
                if ($rp->getGiver()){ //someone give a proxy
                    $session->getFlashBag()->add('error', 'Oups, '. $rp->getGiver() .' a donné une procuration à '. $rp->getOwner() .', il compte dessus !');
                    return $this->redirectToRoute('homepage');
                } else { // no-one give a proxy, lets remove the waiting one
                    $em->remove($rp);
                    //$em->flush();
                }
            }
        }

        // check if member is allowed to vote
        $registrationDuration = $this->getParameter('registration_duration');
        if ($registrationDuration) {
            $minDateOfLastRegistration = clone $event->getMaxDateOfLastRegistration();
            $minDateOfLastRegistration->modify('-'.$registrationDuration);
            if ($membership->getLastRegistration()->getDate() < $minDateOfLastRegistration){
                $session->getFlashBag()->add('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré après le '.
                    $minDateOfLastRegistration->format('d M Y').
                    ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
                return $this->redirectToRoute('homepage');
            }
        }
        if (!$membership->hasValidRegistrationBefore($event->getMaxDateOfLastRegistration())){
            $session->getFlashBag()->add('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré avant le '.
                $event->getMaxDateOfLastRegistration()->format('d M Y').
                ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
            return $this->redirectToRoute('homepage');
        }

        // default proxy form
        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('event_proxy_give', array('id' => $event->getId())))
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        // anonymousProxy ?
        if ($form->isSubmitted() && $form->isValid()) {
            $proxy = $em->getRepository('AppBundle:Proxy')->findOneBy(array("event"=>$event, "giver"=>null));
            if (!$proxy){
                $proxy = new Proxy();
                $proxy->setEvent($event);
                $proxy->setCreatedAt(new \DateTime());
            }

            $proxy->setGiver($current_app_user->getBeneficiary()->getMembership());
            $em->persist($proxy);
            $em->flush();
            $session = new Session();
            $session->getFlashBag()->add('success', 'Procuration acceptée !');

            if ($proxy->getGiver() && $proxy->getOwner()){
                $this->sendProxyMail($proxy,$mailer);
            }

            return $this->redirectToRoute('homepage');
        }

        // proxy with a given beneficiary
        if ($request->get("beneficiary") > 0) {
            $em = $this->getDoctrine()->getManager();
            $beneficiary = $em->getRepository('AppBundle:Beneficiary')->find($request->get("beneficiary"));
            if ($beneficiary) {
                // check if member hasn't already given a proxy
                $member_giver_proxies = $em->getRepository('AppBundle:Proxy')->findBy(
                    array("giver" => $beneficiary->getMembership(), "event" => $event)
                );
                if (count($member_giver_proxies) > 0) {
                    $session->getFlashBag()->add('error', $beneficiary->getPublicDisplayNameWithMemberNumber() . ' a déjà donné sa procuration');
                    return $this->redirectToRoute('homepage');
                }

                // check if member doesn't already have maximum number of procuration(s)
                $beneficiaries_ids = [];
                foreach ($beneficiary->getMembership()->getBeneficiaries() as $b) {
                    $beneficiaries_ids[] = $b;
                }
                $member_owner_proxies = $em->getRepository('AppBundle:Proxy')->findBy(
                    array("owner" => $beneficiaries_ids, "event" => $event)
                );
                if (count($member_owner_proxies) >= $max_event_proxy_per_member) {
                    $session->getFlashBag()->add('error', $beneficiary->getPublicDisplayNameWithMemberNumber() . ' accepte déjà de prendre le nombre maximal de procurations ('. $max_event_proxy_per_member .')');
                    return $this->redirectToRoute('homepage');
                }

                // create proxy
                $proxy = new Proxy();
                $proxy->setEvent($event);
                $proxy->setCreatedAt(new \DateTime());
                $proxy->setOwner($beneficiary);
                $proxy->setGiver($current_app_user->getBeneficiary()->getMembership());

                $confirm_form = $this->createForm(ProxyType::class, $proxy);
                $confirm_form->handleRequest($request);

                if ($confirm_form->isSubmitted() && $confirm_form->isValid()) {
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($proxy);
                    $em->flush();
                    $session = new Session();
                    $session->getFlashBag()->add('success', 'Procuration donnée à '. $proxy->getOwner()->getMembership()->getMemberNumberWithBeneficiaryListString() .' !');

                    if ($proxy->getGiver() && $proxy->getOwner()) {
                        $this->sendProxyMail($proxy, $mailer);
                    }

                    return $this->redirectToRoute('homepage');
                }

                return $this->render('default/event/proxy/give.html.twig', array(
                    'event' => $event,
                    'form' => $form->createView(),
                    'confirm_form' => $confirm_form->createView(),
                ));

            }else{
                return $this->redirectToRoute('homepage');
            }
        }

        // search beneficiary whom to give proxy
        $search_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('event_proxy_find_beneficiary', array('id' => $event->getId())))
            ->add('firstname', TextType::class, array('label' => 'le prénom'))
            ->setMethod('POST')
            ->getForm();

        return $this->render('default/event/proxy/give.html.twig', array(
            'event' => $event,
            'form' => $form->createView(),
            'search_form' => $search_form->createView()
        ));
    }

    /**
     * Generate a page for a beneficiary to choose a proxy able to vote for an event.
     * Automatically remove:
     * - the withdrawn members
     * - and if a registration_duration is defined, the members with an expired registration
     *
     * Goes with the Twig template views/beneficiary/find_member_number.html.twig
     * @Route("/{id}/proxy/find_beneficiary", name="event_proxy_find_beneficiary", methods={"POST"})
     */
    public function findBeneficiaryAction(Event $event, Request $request)
    {
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $membership = $current_app_user->getBeneficiary()->getMembership();

        $minLastRegistration = clone $event->getMaxDateOfLastRegistration();
        $registrationDuration = $this->getParameter('registration_duration');
        $minLastRegistration->modify('-'.$registrationDuration);

        $search_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('event_proxy_find_beneficiary', array('id' => $event->getId())))
            ->add('firstname', TextType::class, array('label' => 'le prénom'))
            ->setMethod('POST')
            ->getForm();

        if ($search_form->handleRequest($request)->isValid()) {
            $firstname = $search_form->get('firstname')->getData();
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            $beneficiaries_request = $qb->select('b')->from('AppBundle\Entity\Beneficiary', 'b')
                ->join('b.user', 'u')
                ->join('b.membership', 'm')
                ->leftJoin("m.registrations", "r")
                ->where( $qb->expr()->like('b.firstname', $qb->expr()->literal('%'.$firstname.'%')))
                ->andWhere("m.withdrawn != 1 or m.withdrawn is NULL" )
                ->andWhere("m != :current_member" )
                    ->setParameter('current_member',$membership);

            if (!is_null($registrationDuration)){
                $beneficiaries_request = $beneficiaries_request
                    ->andWhere('r.date >= :min_last_registration')
                        ->setParameter('min_last_registration', $minLastRegistration)
                    ->andWhere('r.date < :max_last_registration')
                        ->setParameter('max_last_registration', $event->getMaxDateOfLastRegistration()) ;
            }

            $beneficiaries = $beneficiaries_request
                ->orderBy("m.member_number", 'ASC')
                ->getQuery()
                ->getResult();

            $min_time_count = $this->container->getParameter("time_after_which_members_are_late_with_shifts");

            $filtered_beneficiaries = array_filter(
                $beneficiaries,
                function($b) use ($min_time_count) { return $b->getMembership()->getShiftTimeCount()>$min_time_count*60; }
            );

            if (count($filtered_beneficiaries) != count($beneficiaries)){
                $session->getFlashBag()->add('notice', "Certains bénéficiaires ne sont pas présents dans " .
                    "cette liste, car leur compte est en dessous de la limite d'heure de retard.");
            }

            return $this->render('beneficiary/find_member_number.html.twig', array(
                'form' => null,
                'beneficiaries' => $filtered_beneficiaries,
                'return_path' => 'event_proxy_give',
                'routeParam' => 'beneficiary',
                'params' => ['id' => $event->getId()]
            ));
        }

        $session->getFlashBag()->add('error', "oups, quelque chose c'est mal passé");
        return $this->redirectToRoute("event_proxy_give", array('id'=>$event->getId()));
    }

    /**
     * Proxy take
     *
     * @Route("/{event}/proxy/remove/{proxy}", name="event_proxy_lite_remove", methods={"GET"})
     */
    public function removeProxyLiteAction(Event $event, Proxy $proxy, Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        if (($proxy->getEvent() === $event) && ($proxy->getOwner()->getUser() == $current_app_user)) {
            $em->remove($proxy);
            $em->flush();

            $session->getFlashBag()->add('success', 'Ok, bien reçu');
        }

        return $this->redirectToRoute('homepage');
    }

    /**
     * Proxy take
     *
     * @Route("/{id}/proxy/take", name="event_proxy_take", methods={"GET","POST"})
     */
    public function acceptProxyAction(Event $event, Request $request, \Swift_Mailer $mailer)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        // check if member hasn't already given a proxy
        $myproxy = $em->getRepository('AppBundle:Proxy')->findOneBy(
            array("event" => $event, "giver" => $current_app_user->getBeneficiary()->getMembership())
        );
        if ($myproxy) {
            $session->getFlashBag()->add('error', 'Oups, tu as déjà donné une procuration');
            return $this->redirectToRoute('homepage');
        }

        // check if member is allowed to vote
        $registrationDuration = $this->getParameter('registration_duration');
        if ($registrationDuration) {
            $minDateOfLastRegistration = clone $event->getMaxDateOfLastRegistration();
            $minDateOfLastRegistration->modify('-'.$registrationDuration);
            if ($current_app_user->getBeneficiary()->getMembership()->getLastRegistration()->getDate() < $minDateOfLastRegistration ){
                $session->getFlashBag()->add('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré après le '.
                    $minDateOfLastRegistration->format('d M Y').
                    ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
                return $this->redirectToRoute('homepage');
            }
        }
        if (!$current_app_user->getBeneficiary()->getMembership()->hasValidRegistrationBefore($event->getMaxDateOfLastRegistration())){
            $session->getFlashBag()->add('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré avant le '.
                $event->getMaxDateOfLastRegistration()->format('d M Y').
                ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
            return $this->redirectToRoute('homepage');
        }

        $proxy = $em->getRepository('AppBundle:Proxy')->findOneBy(array("event" => $event, "owner" => null));
        if (!$proxy){
            $proxy = new Proxy();
            $proxy->setEvent($event);
            $proxy->setCreatedAt(new \DateTime());
        }
        $form = $this->createForm(ProxyType::class, $proxy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // check if member doesn't already have the maximum nomber of proxies (%max_event_proxy_per_member%)
            $max_event_proxy_per_member = $this->container->getParameter("max_event_proxy_per_member");
            $myproxy = $em->getRepository('AppBundle:Proxy')->findBy(array("event" => $event, "owner" => $form->getData()->getOwner()));
            if (count($myproxy) >= $max_event_proxy_per_member) {
                $session->getFlashBag()->add('error', $myproxy->getOwner()->getFirstname().' accepte déjà '. $max_event_proxy_per_member .' procuration.');
                return $this->redirectToRoute('event_proxy_take', array('id'=>$event->getId()));
            }

            // save proxy
            $em->persist($proxy);
            $em->flush();
            $session->getFlashBag()->add('success', 'Procuration acceptée !');

            if ($proxy->getGiver() && $proxy->getOwner()){
                $this->sendProxyMail($proxy,$mailer);
            }

            return $this->redirectToRoute('homepage');
        }

        return $this->render('default/event/proxy/take.html.twig', array(
            'event' => $event,
            'form' => $form->createView()
        ));
    }

    public function sendProxyMail(Proxy $proxy, \Swift_Mailer $mailer)
    {
        $giverMainBeneficiary = $proxy->getGiver()->getMainBeneficiary();

        $memberEmail = $this->getParameter('emails.member');
        $owner = (new \Swift_Message('['.$proxy->getEvent()->getTitle().'] procuration'))
            ->setFrom($memberEmail['address'], $memberEmail['from_name'])
            ->setTo([$proxy->getOwner()->getEmail() => $proxy->getOwner()->getFirstname() . ' ' . $proxy->getOwner()->getLastname()])
            ->setReplyTo([$giverMainBeneficiary->getEmail() => $giverMainBeneficiary->getFirstname() . ' ' . $giverMainBeneficiary->getLastname()])
            ->setBody(
                $this->renderView(
                    'emails/proxy_owner.html.twig',
                    array(
                        'proxy' => $proxy,
                        'giverMainBeneficiary' => $giverMainBeneficiary
                    )
                ),
                'text/html'
            );
        $giver = (new \Swift_Message('['.$proxy->getEvent()->getTitle().'] ta procuration'))
            ->setFrom($memberEmail['address'], $memberEmail['from_name'])
            ->setTo([$giverMainBeneficiary->getEmail() => $giverMainBeneficiary->getFirstname() . ' ' . $giverMainBeneficiary->getLastname()])
            ->setReplyTo([$proxy->getOwner()->getEmail() => $proxy->getOwner()->getFirstname() . ' ' . $proxy->getOwner()->getLastname()])
            ->setBody(
                $this->renderView(
                    'emails/proxy_giver.html.twig',
                    array(
                        'proxy' => $proxy,
                        'giverMainBeneficiary' => $giverMainBeneficiary
                    )
                ),
                'text/html'
            );
        $mailer->send($owner);
        $mailer->send($giver);
    }
}
