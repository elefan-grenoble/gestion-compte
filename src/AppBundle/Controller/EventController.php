<?php

namespace AppBundle\Controller;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Event;
use AppBundle\Entity\Proxy;
use AppBundle\Form\EventType;
use AppBundle\Form\ProxyType;
use AppBundle\Repository\EventKindRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * Event controller.
 *
 * @Route("event")
 */
class EventController extends Controller
{
    /**
     * Filter form.
     */
    private function filterFormFactory(Request $request): array
    {
        // default values
        $res = [
            "kind" => null,
            'page' => 1,
        ];

        // filter creation ----------------------
        $res["form"] = $this->createFormBuilder()
            ->setAction($this->generateUrl('event_list'))
            ->add('kind', EntityType::class, array(
                'label' => 'Type',
                'class' => 'AppBundle:EventKind',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
                'query_builder' => function (EventKindRepository $repository) {
                    return $repository->createQueryBuilder('ek')
                        ->orderBy('ek.name', 'ASC');
                },
            ))
            ->add('page', HiddenType::class, [
                'data' => '1'
            ])
            ->add('submit', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ))
            ->getForm();

        $res["form"]->handleRequest($request);

        if ($res["form"]->isSubmitted() && $res["form"]->isValid()) {
            $res["kind"] = $res["form"]->get("kind")->getData();
            $res["page"] = $res["form"]->get("page")->getData();
        }

        return $res;
    }

    /**
     * Event home page
     *
     * @Route("/", name="event_index", methods={"GET"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $eventsFuture = $em->getRepository('AppBundle:Event')->findFutures();
        $eventsPast = $em->getRepository('AppBundle:Event')->findPast(10);  # only the 10 last

        return $this->render('admin/event/index.html.twig', array(
            'eventsFuture' => $eventsFuture,
            'eventsPast' => $eventsPast,
        ));
    }

    /**
     * Event list
     *
     * @Route("/list", name="event_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $filter = $this->filterFormFactory($request);
        $sort = 'date';
        $order = 'DESC';

        $qb = $em->getRepository('AppBundle:Event')->createQueryBuilder('e')
            ->orderBy('e.' . $sort, $order);

        if ($filter['kind']) {
            $qb = $qb->andWhere('e.kind = :kind')
                ->setParameter('kind', $filter['kind']);
        }

        $limitPerPage = 25;
        $paginator = new Paginator($qb);
        $resultCount = count($paginator);
        $pageCount = ($resultCount == 0) ? 1 : ceil($resultCount / $limitPerPage);
        $currentPage = $filter['page'];
        $currentPage = ($currentPage > $pageCount) ? $pageCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage-1)) // set the offset
            ->setMaxResults($limitPerPage); // set the limit

        return $this->render('admin/event/list.html.twig', array(
            'events' => $paginator,
            'filter_form' => $filter['form']->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount,
        ));
    }

    /**
     * Event new
     *
     * @Route("/new", name="event_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setCreatedBy($current_user);
            $em->persist($event);
            $em->flush();

            $session->getFlashBag()->add('success', 'L\'événement a bien été créé !');
            return $this->redirectToRoute('event_edit', array('id' => $event->getId()));
        }

        return $this->render('admin/event/new.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Event edit
     *
     * @Route("/{id}/edit", name="event_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, Event $event)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setUpdatedBy($current_user);
            $em->persist($event);
            $em->flush();

            $session->getFlashBag()->add('success', 'L\'événement a bien été édité !');
            return $this->redirectToRoute('event_index');
        }

        return $this->render('admin/event/edit.html.twig', array(
            'form' => $form->createView(),
            'event' => $event,
            'delete_form' => $this->getDeleteForm($event)->createView(),
        ));
    }

    /**
     * Event delete
     *
     * @Route("/{id}", name="event_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request, Event $event)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->getDeleteForm($event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($event);
            $em->flush();

            $session->getFlashBag()->add('success', 'L\'événement a bien été supprimé !');
        }

        return $this->redirectToRoute('event_index');
    }

    /**
     * Lists all proxy
     *
     * @Route("/proxies", name="proxies_list", methods={"GET"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function listProxiesAction()
    {
        $em = $this->getDoctrine()->getManager();

        $proxies = $em->getRepository('AppBundle:Proxy')->findAll();

        $delete_forms = array();
        foreach ($proxies as $proxy){
            $delete_forms[$proxy->getId()] = $this->getProxyDeleteForm($proxy)->createView();
        }

        return $this->render('admin/event/proxy/list.html.twig', array(
            'proxies' => $proxies,
            'delete_forms' => $delete_forms,
            'event' => null,
        ));
    }

    /**
     * Lists all proxy for one event.
     *
     * @Route("/{id}/proxies", name="event_proxies_list", methods={"GET"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function listEventProxiesAction(Event $event, Request $request)
    {
        $proxies = $event->getProxies();

        $delete_forms = array();
        foreach ($proxies as $proxy) {
            $delete_forms[$proxy->getId()] = $this->getProxyDeleteForm($proxy)->createView();
        }

        return $this->render('admin/event/proxy/list.html.twig', array(
            'proxies' => $proxies,
            'delete_forms' => $delete_forms,
            'event' => $event,
        ));
    }

    /**
     * Proxy delete
     *
     * @Route("/proxies/{id}", name="proxy_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function deleteProxyAction(Request $request, Proxy $proxy)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $form = $this->getProxyDeleteForm($proxy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($proxy);
            $em->flush();

            $session->getFlashBag()->add('success', 'La procuration a bien été supprimée !');
        }

        return $this->redirectToRoute('event_proxies_list', array('id'=>$proxy->getEvent()->getId()));
    }

    /**
     * Proxy edit
     *
     * @Route("/proxies/{id}", name="proxy_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function editProxyAction(Request $request, Proxy $proxy, \Swift_Mailer $mailer)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $event = $proxy->getEvent();
        $form = $this->createForm(ProxyType::class, $proxy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($proxy->getOwner()){
                $existing_proxy = $em->getRepository('AppBundle:Proxy')->findOneBy(array("event"=>$event,"owner"=>$proxy->getOwner()));
                if ($existing_proxy && $existing_proxy != $proxy){
                    $session->getFlashBag()->add('error', $existing_proxy->getOwner()->getFirstname().' accepte déjà une procuration.');
                    return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
                }
            }
            if ($proxy->getGiver()){
                $existing_proxy = $em->getRepository('AppBundle:Proxy')->findOneBy(array("event"=>$event,"giver"=>$proxy->getGiver()));
                if ($existing_proxy && $existing_proxy != $proxy){
                    $session->getFlashBag()->add('error', $existing_proxy->getGiver()->getFirstname().' donne déjà une procuration.');
                    return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
                }
            }
            if (!$proxy->getOwner() && $proxy->getGiver()){
                $proxy_waiting = $em->getRepository('AppBundle:Proxy')->findOneBy(array("event"=>$event,"giver"=>null));
                if ($proxy_waiting && $proxy_waiting != $proxy){
                    $proxy_waiting->setGiver($proxy->getGiver());
                    $em->persist($proxy_waiting);
                    $em->remove($proxy);
                    $em->flush();
                    $session->getFlashBag()->add('success', 'proxy '.$proxy->getId().' deleted');
                    $session->getFlashBag()->add('success', 'proxy '.$proxy_waiting->getId().' updated');
                    $session->getFlashBag()->add('success', $proxy_waiting->getGiver().' => '.$proxy_waiting->getOwner());
                    $this->sendProxyMail($proxy_waiting,$mailer);
                    return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
                }
                $em->persist($proxy);
                $em->flush();
                $session->getFlashBag()->add('success', 'proxy '.$proxy->getId().' saved');
                return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
            }elseif ($proxy->getOwner() && !$proxy->getGiver()){
                $proxy_waiting = $em->getRepository('AppBundle:Proxy')->findOneBy(array("event"=>$event,"owner"=>null));
                if ($proxy_waiting && $proxy_waiting != $proxy){
                    $proxy_waiting->setOwner($proxy->getOwner());
                    $em->persist($proxy_waiting);
                    $em->remove($proxy);
                    $em->flush();
                    $session->getFlashBag()->add('success', 'proxy '.$proxy->getId().' deleted');
                    $session->getFlashBag()->add('success', 'proxy '.$proxy_waiting->getId().' updated');
                    $session->getFlashBag()->add('success', $proxy_waiting->getGiver().' => '.$proxy_waiting->getOwner());
                    $this->sendProxyMail($proxy_waiting,$mailer);
                    return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
                }
                $em->persist($proxy);
                $em->flush();
                $session->getFlashBag()->add('success', 'proxy '.$proxy->getId().' saved');
                return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
            }elseif ($proxy->getOwner() && $proxy->getGiver()){
                $em->persist($proxy);
                $em->flush();
                $session->getFlashBag()->add('success', 'proxy '.$proxy->getId().' saved');
                $session->getFlashBag()->add('success', $proxy->getGiver().' => '.$proxy->getOwner());
                $this->sendProxyMail($proxy,$mailer);
                return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
            }

            return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
        }

        return $this->render('admin/event/proxy/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $this->getProxyDeleteForm($proxy)->createView(),
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

    /**
     * Generate a printable list Signatures list. Automatically remove the
     * withdrawn members and if a registration_duration is defined, the
     * member with an expired registration.
     *
     * Goes with the twig template views/admin/event/signatures.html.twig
     *
     * @Route("/{id}/signatures/", name="event_signatures", methods={"GET","POST"})
     */
    public function signaturesListAction(Request $request,Event $event): Response
    {
        $em = $this->getDoctrine()->getManager();

        $beneficiaries_request = $em->getRepository("AppBundle:Beneficiary")->createQueryBuilder('b')
                ->leftJoin('b.membership', 'm')
                ->leftJoin("m.registrations", "r")
                ->andWhere("r.date is NOT NULL" )
                ->andWhere("m.withdrawn != 1 or m.withdrawn is NULL" );

        if (!is_null($registrationDuration = $this->getParameter('registration_duration'))) {
            $minLastRegistration = clone $event->getMaxDateOfLastRegistration();
            $minLastRegistration->modify('-'.$registrationDuration);

            $beneficiaries_request = $beneficiaries_request
                ->andWhere('r.date >= :min_last_registration')
                ->setParameter('min_last_registration', $minLastRegistration)
                ->andWhere('r.date < :max_last_registration')
                ->setParameter('max_last_registration', $event->getMaxDateOfLastRegistration());
        }

        $beneficiaries = $beneficiaries_request
            ->orderBy("b.lastname", 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/event/signatures.html.twig', array(
            'event' => $event,
            'beneficiaries' => $beneficiaries,
        ));
    }

    /**
     * Event widget generator
     *
     * @Route("/widget_generator", name="event_widget_generator", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function widgetGeneratorAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('kind', EntityType::class, array(
                'label' => "Quel type d'événement ?",
                'class' => 'AppBundle:EventKind',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => true
            ))
            ->add('title', CheckboxType::class, array('required' => false, 'data' => true, 'label' => 'Afficher le titre ?'))
            ->add('generate', SubmitType::class, array('label' => 'Générer'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {
            $data = $form->getData();

            $widgetQueryString = 'event_kind_id='.$data['kind']->getId().'&title='.($data['title'] ? 1 : 0);

            return $this->render('admin/event/widget/generate.html.twig', array(
                'query_string' => $widgetQueryString,
                'form' => $form->createView(),
            ));
        }

        return $this->render('admin/event/widget/generate.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Event widget display
     * 
     * @Route("/widget", name="event_widget", methods={"GET"})
     */
    public function widgetAction(Request $request)
    {
        $buckets = array();
        $eventKind = null;

        $event_kind_id = $request->get('event_kind_id');
        $title = $request->query->has('title') ? ($request->get('title') == 1) : true;

        if ($event_kind_id) {
            $em = $this->getDoctrine()->getManager();
            $eventKind = $em->getRepository('AppBundle:EventKind')->find($event_kind_id);
            if ($eventKind) {
                $events = $em->getRepository('AppBundle:Event')->findFutures($eventKind);
            }
        }

        return $this->render('admin/event/widget/widget.html.twig', [
            'events' => $events,
            'eventKind' => $eventKind,
            'title' => $title
        ]);
    }


    /**
     * @param Event $event
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Event $event)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('event_delete', array('id' => $event->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * @param Proxy $proxy
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getProxyDeleteForm(Proxy $proxy)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('proxy_delete', array('id' => $proxy->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    public function sendProxyMail(Proxy $proxy, \Swift_Mailer $mailer){

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
