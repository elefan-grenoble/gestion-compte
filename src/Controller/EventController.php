<?php

namespace App\Controller;
use App\Entity\Beneficiary;
use App\Entity\Event;
use App\Entity\Proxy;
use App\Form\EventType;
use App\Form\ProxyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;


/**
 * Event controller.
 *
 * @Route("event")
 */
class EventController extends Controller
{

    /**
     * Lists all events.
     *
     * @Route("/", name="event_list")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function listAction(Request $request, EntityManagerInterface $em)
    {
        $events = $em->getRepository('App:Event')->findAll();
        return $this->render('admin/event/list.html.twig', array(
            'events' => $events,
        ));
    }

    /**
     * Lists all proxy
     *
     * @Route("/proxies_list", name="proxies_list")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function listProxiesAction(EntityManagerInterface $em)
    {
        $proxies = $em->getRepository('App:Proxy')->findAll();
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
     * @Route("/{id}/proxies_list", name="event_proxies_list")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function listEventProxiesAction(Event $event, Request $request)
    {
        $proxies = $event->getProxies();
        $delete_forms = array();
        foreach ($proxies as $proxy){
            $delete_forms[$proxy->getId()] = $this->getProxyDeleteForm($proxy)->createView();
        }
        return $this->render('admin/event/proxy/list.html.twig', array(
            'proxies' => $proxies,
            'delete_forms' => $delete_forms,
            'event' => $event,
        ));
    }

    /**
     * Comission new
     *
     * @Route("/new", name="event_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request, EntityManagerInterface $em)
    {
        $session = new Session();
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($event);
            $em->flush();
            $session->getFlashBag()->add('success', 'L\'événement a bien été créé !');
            return $this->redirectToRoute('event_edit', array('id' => $event->getId()));
        }
        return $this->render('admin/event/new.html.twig', array(
            'commission' => $event,
            'form' => $form->createView(),
            'errors' => $form->getErrors()
        ));
    }

    /**
     * Comission edit
     *
     * @Route("/{id}/edit", name="event_edit")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, Event $event, EntityManagerInterface $em)
    {
        $session = new Session();

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($event);
            $em->flush();

            $session->getFlashBag()->add('success', 'L\'événement a bien été édité !');

            return $this->redirectToRoute('event_list');

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
     * @Route("/{id}", name="event_delete")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeAction(Request $request, Event $event, EntityManagerInterface $em)
    {
        $session = new Session();
        $form = $this->getDeleteForm($event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($event);
            $em->flush();
            $session->getFlashBag()->add('success', 'L événement a bien été supprimée !');
        }
        return $this->redirectToRoute('event_list');
    }

    /**
     * Proxy delete
     *
     * @Route("/proxy/{id}", name="proxy_delete")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function removeProxyAction(Request $request, Proxy $proxy, EntityManagerInterface $em)
    {
        $session = new Session();
        $form = $this->getProxyDeleteForm($proxy);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $em->remove($proxy);
            $em->flush();
            $session->getFlashBag()->add('success', 'La procuration a bien été supprimée !');
        }
        return $this->redirectToRoute('event_proxies_list',array('id'=>$proxy->getEvent()->getId()));
    }

    /**
     * Proxy edit
     *
     * @Route("/proxy/{id}", name="proxy_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function editProxyAction(Request $request,Proxy $proxy,\Swift_Mailer $mailer, EntityManagerInterface $em)
    {
        $session = new Session();
        $event = $proxy->getEvent();
        $form = $this->createForm(ProxyType::class, $proxy);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($proxy->getOwner()){
                $existing_proxy = $em->getRepository('App:Proxy')->findOneBy(array("event"=>$event,"owner"=>$proxy->getOwner()));
                if ($existing_proxy && $existing_proxy != $proxy){
                    $session->getFlashBag()->add('error', $existing_proxy->getOwner()->getFirstname().' accepte déjà une procuration.');
                    return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
                }
            }
            if ($proxy->getGiver()){
                $existing_proxy = $em->getRepository('App:Proxy')->findOneBy(array("event"=>$event,"giver"=>$proxy->getGiver()));
                if ($existing_proxy && $existing_proxy != $proxy){
                    $session->getFlashBag()->add('error', $existing_proxy->getGiver()->getFirstname().' donne déjà une procuration.');
                    return $this->redirectToRoute('event_proxies_list',array('id'=>$event->getId()));
                }
            }
            if (!$proxy->getOwner() && $proxy->getGiver()){
                $proxy_waiting = $em->getRepository('App:Proxy')->findOneBy(array("event"=>$event,"giver"=>null));
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
                $proxy_waiting = $em->getRepository('App:Proxy')->findOneBy(array("event"=>$event,"owner"=>null));
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
     * @param Event $event
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Event $event){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('event_delete', array('id' => $event->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * @param Proxy $proxy
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getProxyDeleteForm(Proxy $proxy){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('proxy_delete', array('id' => $proxy->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Proxy new
     *
     * @Route("/{id}/proxy/give", name="event_proxy_give")
     * @Method({"GET", "POST"})
     */
    public function giveProxyAction(Event $event, Request $request,\ Swift_Mailer $mailer, EntityManagerInterface $em)
    {
        $session = new Session();
        $current_app_user = $this->getUser();
        $myproxy = $em->getRepository('App:Proxy')->findOneBy(array("event"=>$event,"giver"=>$current_app_user));

        if ($myproxy){
            $session->getFlashBag()->add('error', 'Oups, tu as déjà donné une procuration');
            return $this->redirectToRoute('homepage');
        }
        $membership = $current_app_user->getBeneficiary()->getMembership();
        $beneficiaries = $membership->getBeneficiaries();
        $beneficiariesId = array_map(function(Beneficiary $beneficiary) {
            return $beneficiary->getId();
        }, $beneficiaries->toArray());
        $received_proxy = $em->getRepository('App:Proxy')->findBy(
            array(
                "owner" => $beneficiariesId,
                "event" => $event
            )
        );
        if ($received_proxy){
            foreach ($received_proxy as $rp){
                if ($rp->getGiver()){ //someone give a proxy
                    $session->getFlashBag()->add('error', 'Oups, '.$rp->getGiver().' a donné une procuration à '.$rp->getOwner().', il compte dessus !');
                    return $this->redirectToRoute('homepage');
                }else{ //no-one give a proxy, lets remove the waiting one
                    $em->remove($rp);
                    //$em->flush();
                }
            }
        }
        if ($membership->getLastRegistration()->getDate() < $event->getMinDateOfLastRegistration()){
            $session->getFlashBag()->add('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré après le '.
                $event->getMinDateOfLastRegistration()->format('d M Y').
                ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
            return $this->redirectToRoute('homepage');
        }

        $form = $this->createFormBuilder()
            ->setAction($this->generateUrl('event_proxy_give', array('id' => $event->getId())))
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $proxy = $em->getRepository('App:Proxy')->findOneBy(array("event"=>$event,"giver"=>null));
            if (!$proxy){
                $proxy = new Proxy();
                $proxy->setEvent($event);
                $proxy->setCreatedAt(new \DateTime());
            }

            $proxy->setGiver($current_app_user->getBeneficiary()->getMembership());
            $em->persist($proxy);
            $em->flush();
            $session = new Session();
            $session->getFlashBag()->add('success', 'Votre réquète a bien été acceptée !');

            if ($proxy->getGiver() && $proxy->getOwner()){
                $this->sendProxyMail($proxy,$mailer);
            }

            return $this->redirectToRoute('homepage');
        }

        if ($request->get("beneficiary") > 0){
            $beneficiary = $em->getRepository('App:Beneficiary')->find($request->get("beneficiary"));
            if ($beneficiary){

                $beneficiaries_ids = [];
                foreach ($beneficiary->getMembership()->getBeneficiaries() as $b){
                    $beneficiaries_ids[] = $b;
                }
                /** @var Proxy $proxy */
                $proxy = $em->getRepository('App:Proxy')->findOneBy(
                    array("owner" => $beneficiaries_ids, "event" => $event)
                );

                if ($proxy) {
                    if ($proxy->getGiver() !== null) {
                        $session->getFlashBag()->add('error', $beneficiary->getUser()->getFirstName() . ' accepte déjà de prendre une procuration d\'une autre personne');
                        return $this->redirectToRoute('homepage');
                    } else if ($proxy->getOwner()!=$beneficiary){
                        $session->getFlashBag()->add('notice', $beneficiary->getUser()->getFirstName() . ' partage son adhésion #' . $beneficiary->getMemberNumber() . ' avec ' . $proxy->getOwner()->getUser()->getFirstname() . ' qui accepte de prendre une procuration pour cet événement !');
                    }
                } else {
                    $proxy = new Proxy();
                    $proxy->setEvent($event);
                    $proxy->setCreatedAt(new \DateTime());
                    $proxy->setOwner($beneficiary);
                }
                $current_app_user = $this->getUser();
                $proxy->setGiver($current_app_user->getBeneficiary()->getMembership());
                $confirm_form = $this->createForm(ProxyType::class, $proxy);
                $confirm_form->handleRequest($request);

                if ($confirm_form->isSubmitted() && $confirm_form->isValid()) {
                    $em->persist($proxy);
                    $em->flush();
                    $session = new Session();
                    $session->getFlashBag()->add('success', 'Votre réquète a bien été acceptée !');

                    if ($proxy->getGiver() && $proxy->getOwner()){
                        $this->sendProxyMail($proxy,$mailer);
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
     * @Route("/{id}/proxy/find_beneficiary", name="event_proxy_find_beneficiary")
     * @Method({"POST"})
     */
    public function findBeneficiaryAction(Event $event, Request $request, EntityManagerInterface $em){
        $search_form = $this->createFormBuilder()
            ->setAction($this->generateUrl('event_proxy_find_beneficiary', array('id' => $event->getId())))
            ->add('firstname', TextType::class, array('label' => 'le prénom'))
            ->setMethod('POST')
            ->getForm();

        $session = new Session();

        if ($search_form->handleRequest($request)->isValid()) {
            $firstname = $search_form->get('firstname')->getData();
            $qb = $em->createQueryBuilder();
            $beneficiaries = $qb->select('b')->from('App\Entity\Beneficiary', 'b')
                ->join('b.user', 'u')
                ->join('b.membership', 'm')
                ->where( $qb->expr()->like('b.firstname', $qb->expr()->literal('%'.$firstname.'%')))
                ->andWhere("m.withdrawn != 1 or m.withdrawn is NULL" )
                ->orderBy("m.member_number", 'ASC')
                ->getQuery()
                ->getResult();
            return $this->render('beneficiary/find_member_number.html.twig', array(
                'form' => null,
                'beneficiaries' => $beneficiaries,
                'return_path' => 'event_proxy_give',
                'routeParam' => 'beneficiary',
                'params' => ['id' => $event->getId()]
            ));
        }
        $session->getFlashBag()->add('error',"oups, quelque chose c'est mal passé");
        return $this->redirectToRoute("event_proxy_give",array('id'=>$event->getId()));
    }

    /**
     * Proxy take
     *
     * @Route("/{event}/proxy/remove/{proxy}", name="event_proxy_lite_remove")
     * @Method({"GET"})
     */
    public function removeProxyLiteAction(Event $event, Proxy $proxy, Request $request, EntityManagerInterface $em){
        $session = new Session();
        $current_app_user = $this->getUser();
        if ($proxy->getEvent() === $event && $proxy->getOwner()->getUser() == $current_app_user) {
            $em->remove($proxy);
            $em->flush();
            $session->getFlashBag()->add('success', 'Ok, bien reçu');
        }
        return $this->redirectToRoute('homepage');
    }

    /**
     * Proxy take
     *
     * @Route("/{id}/proxy/take", name="event_proxy_take")
     * @Method({"GET","POST"})
     */
    public function acceptProxyAction(Event $event, Request $request, \Swift_Mailer $mailer, EntityManagerInterface $em)
    {
        $current_app_user = $this->getUser();
        $myproxy = $em->getRepository('App:Proxy')->findOneBy(array("event"=>$event,"giver"=>$current_app_user));
        $session = new Session();
        if ($myproxy){
            $session->getFlashBag()->add('error', 'Oups, tu as déjà donné une procuration');
            return $this->redirectToRoute('homepage');
        }
        if ($current_app_user->getBeneficiary()->getMembership()->getLastRegistration()->getDate() < $event->getMinDateOfLastRegistration()){
            $session->getFlashBag()->add('error', 'Oups, seuls les membres qui ont adhéré ou ré-adhéré après le '.
                $event->getMinDateOfLastRegistration()->format('d M Y').
                ' peuvent voter à cet événement. Pense à mettre à jour ton adhésion pour participer !');
            return $this->redirectToRoute('homepage');
        }
        $proxy = $em->getRepository('App:Proxy')->findOneBy(array("event"=>$event,"owner"=>null));
        if (!$proxy){
            $proxy = new Proxy();
            $proxy->setEvent($event);
            $proxy->setCreatedAt(new \DateTime());
        }
        $form = $this->createForm(ProxyType::class, $proxy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $myproxy = $em->getRepository('App:Proxy')->findOneBy(array("event"=>$event,"owner"=>$form->getData()->getOwner()));
            if ($myproxy){
                $session->getFlashBag()->add('error', $myproxy->getOwner()->getFirstname().' accepte déjà une procuration.');
                return $this->redirectToRoute('event_proxy_take',array('id'=>$event->getId()));
            }
            $em->persist($proxy);
            $em->flush();
            $session->getFlashBag()->add('success', 'Votre réquète a bien été acceptée !');

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

    public function sendProxyMail(Proxy $proxy, \Swift_Mailer $mailer, $memberEmail){

        $giverMainBeneficiary = $proxy->getGiver()->getMainBeneficiary();
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
        $giver = (new \Swift_Message('['.$proxy->getEvent()->getTitle().'] votre procuration'))
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

    /**
     * signatures list
     *
     * @Route("/{id}/signatures/", name="event_signatures")
     * @Method({"GET","POST"})
     */
    public function signaturesListAction(Request $request, Event $event, EntityManagerInterface $em)
    {
        return $this->render('admin/event/signatures.html.twig', array(
            'event' => $event,
            'beneficiaries' => $em->getRepository('App:Beneficiary')->findBy(array(),array('lastname'=>'ASC'))
        ));
    }
}
