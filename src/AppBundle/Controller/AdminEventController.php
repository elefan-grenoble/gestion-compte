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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * Admin Event controller
 *
 * @Route("admin/events")
 */
class AdminEventController extends Controller
{
    /**
     * Filter form
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
            ->setAction($this->generateUrl('admin_event_list'))
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
     * Admin event home
     *
     * @Route("/", name="admin_event_index", methods={"GET"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $eventsFuture = $em->getRepository('AppBundle:Event')->findFutures();
        $eventsOngoing = $em->getRepository('AppBundle:Event')->findOngoing();
        $eventsPast = $em->getRepository('AppBundle:Event')->findPast(null, 10);  # only the 10 last

        return $this->render('admin/event/index.html.twig', array(
            'eventsFuture' => $eventsFuture,
            'eventsOngoing' => $eventsOngoing,
            'eventsPast' => $eventsPast,
        ));
    }

    /**
     * Admin event list
     *
     * @Route("/list", name="admin_event_list", methods={"GET","POST"})
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
     * @Route("/new", name="admin_event_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
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
            return $this->redirectToRoute('admin_event_edit', array('id' => $event->getId()));
        }

        return $this->render('admin/event/new.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * Event edit
     *
     * @Route("/{id}/edit", name="admin_event_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
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
            return $this->redirectToRoute('admin_event_index');
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
     * @Route("/{id}", name="admin_event_delete", methods={"DELETE"})
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

        return $this->redirectToRoute('admin_event_index');
    }

    /**
     * Lists all proxy
     *
     * @Route("/proxies", name="admin_proxies_list", methods={"GET"})
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
     * Lists all proxy for one event
     *
     * @Route("/{id}/proxies", name="admin_event_proxies_list", methods={"GET"})
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
     * @Route("/proxies/{id}", name="admin_proxy_delete", methods={"DELETE"})
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
     * @Route("/proxies/{id}", name="admin_proxy_edit", methods={"GET","POST"})
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
                    return $this->redirectToRoute('admin_event_proxies_list',array('id'=>$event->getId()));
                }
            }
            if ($proxy->getGiver()){
                $existing_proxy = $em->getRepository('AppBundle:Proxy')->findOneBy(array("event"=>$event,"giver"=>$proxy->getGiver()));
                if ($existing_proxy && $existing_proxy != $proxy){
                    $session->getFlashBag()->add('error', $existing_proxy->getGiver()->getFirstname().' donne déjà une procuration.');
                    return $this->redirectToRoute('admin_event_proxies_list',array('id'=>$event->getId()));
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
                    return $this->redirectToRoute('admin_event_proxies_list',array('id'=>$event->getId()));
                }
                $em->persist($proxy);
                $em->flush();
                $session->getFlashBag()->add('success', 'proxy '.$proxy->getId().' saved');
                return $this->redirectToRoute('admin_event_proxies_list',array('id'=>$event->getId()));
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
                    return $this->redirectToRoute('admin_event_proxies_list',array('id'=>$event->getId()));
                }
                $em->persist($proxy);
                $em->flush();
                $session->getFlashBag()->add('success', 'proxy '.$proxy->getId().' saved');
                return $this->redirectToRoute('admin_event_proxies_list',array('id'=>$event->getId()));
            }elseif ($proxy->getOwner() && $proxy->getGiver()){
                $em->persist($proxy);
                $em->flush();
                $session->getFlashBag()->add('success', 'proxy '.$proxy->getId().' saved');
                $session->getFlashBag()->add('success', $proxy->getGiver().' => '.$proxy->getOwner());
                $this->sendProxyMail($proxy,$mailer);
                return $this->redirectToRoute('admin_event_proxies_list',array('id'=>$event->getId()));
            }

            return $this->redirectToRoute('admin_event_proxies_list',array('id'=>$event->getId()));
        }

        return $this->render('admin/event/proxy/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $this->getProxyDeleteForm($proxy)->createView(),
        ));
    }


    /**
     * Generate a printable list Signatures list.
     * Automatically remove the withdrawn members and if a registration_duration is defined,
     * the member with an expired registration.
     *
     * @Route("/{id}/signatures/", name="admin_event_signatures", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
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
     * @Route("/widget_generator", name="admin_event_widget_generator", methods={"GET","POST"})
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
                'required' => false
            ))
            ->add('date_max', TextType::class, array(
                'label' => "Jusqu'à la date (incluse) ?",
                'required' => false,
                'attr' => array('class' => 'datepicker')
            ))
            ->add('limit', IntegerType::class, array(
                'label' => "Nombre maximum d'événements à afficher ?",
                'scale' => 0,
                'required' => false
            ))
            ->add('title', CheckboxType::class, array(
                'label' => 'Afficher le titre du widget ?',
                'data' => false,
                'required' => false,
                'attr' => array('class' => 'filled-in')
            ))
            ->add('links', CheckboxType::class, array(
                'label' => 'Afficher un lien vers l\'événement ?',
                'data' => false,
                'required' => false,
                'attr' => array('class' => 'filled-in')
            ))
            ->add('generate', SubmitType::class, array('label' => 'Générer'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {
            $data = $form->getData();

            $widgetQueryString = 'event_kind_id=' . ($data['kind'] ? $data['kind']->getId() : '') . '&date_max=' . ($data['date_max'] ? $data['date_max'] : '') . '&limit=' . ($data['limit'] ? $data['limit'] : '') . '&title=' . ($data['title'] ? 1 : 0) . '&links=' . ($data['links'] ? 1 : 0);

            return $this->render('admin/event/widget_generator.html.twig', array(
                'form' => $form->createView(),
                'query_string' => $widgetQueryString
            ));
        }

        return $this->render('admin/event/widget_generator.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * @param Event $event
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Event $event)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_event_delete', array('id' => $event->getId())))
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
            ->setAction($this->generateUrl('admin_proxy_delete', array('id' => $proxy->getId())))
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
