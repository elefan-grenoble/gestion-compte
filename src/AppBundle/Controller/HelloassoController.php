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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Registrations controller.
 *
 * @Route("helloasso")
 */
class HelloassoController extends Controller
{

    /**
     * Helloasso payments list
     *
     * @Route("/payments", name="helloasso_payments", methods={"GET"})
     * @Security("has_role('ROLE_FINANCE_MANAGER')")
     */
    public function helloassoPaymentsAction(Request $request)
    {
        if (!($page = $request->get('page'))) {
            $page = 1;
        }
        $limit = 50;
        $max = $this->getDoctrine()->getManager()->createQueryBuilder()->from('AppBundle\Entity\HelloassoPayment', 'n')
            ->select('count(n.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $nb_of_pages = intval($max / $limit);
        if ($max > 0) {
            $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        }
        $payments = $this->getDoctrine()->getManager()
            ->getRepository('AppBundle:HelloassoPayment')
            ->findBy(array(), array('createdAt' => 'DESC', 'date' => 'DESC'), $limit, ($page - 1) * $limit);
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
     * @Route("/browser", name="helloasso_browser", methods={"GET"})
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
            if (!$campaign_json){
                $session = new Session();
                $session->getFlashBag()->add('error','campaign not found');
                return $this->redirectToRoute('helloasso_browser');
            }
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
     * @Route("/manualPaimentAdd/", name="helloasso_manual_paiement_add", methods={"POST"})
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
     * @Route("/payments/{id}", name="helloasso_payment_remove", methods={"DELETE"})
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
     * resolve orphan payment
     *
     * @Route("/payment/{id}/resolve_orphan/{code}", name="helloasso_resolve_orphan", methods={"GET"})
     * @Security("has_role('ROLE_USER')")
     */
    public function resolveOrphan(HelloassoPayment $payment,$code){
        $code = urldecode($code);
        $email = $this->get('AppBundle\Helper\SwipeCard')->vigenereDecode($code);
        $session = new Session();
        if ($email == $payment->getEmail()){
            if ($payment->getRegistration()){
                $session->getFlashBag()->add('error', 'Le paiement helloasso que tu cherches à corriger n\'a plus besoin de ton aide !');
            }else{
                return $this->render(
                    'user/helloasso_resolve_orphan.html.twig',
                    array('payment' => $payment));
            }
        }else{
            $session->getFlashBag()->add('error', 'Oups, ce lien ne semble pas fonctionner !');
        }
        return $this->redirectToRoute('homepage');
    }

    /**
     * confirm resolve orphan payment
     *
     * @Route("/payment/{id}/confirm_resolve_orphan/{code}", name="helloasso_confirm_resolve_orphan", methods={"GET"})
     * @Security("has_role('ROLE_USER')")
     */
    public function confirmOrphan(HelloassoPayment $payment,$code){
        $code = urldecode($code);
        $email = $this->get('AppBundle\Helper\SwipeCard')->vigenereDecode($code);
        $session = new Session();
        if ($email == $payment->getEmail()) {
            $session->getFlashBag()->add('success', 'Merci !');
            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(
                HelloassoEvent::ORPHAN_SOLVE,
                new HelloassoEvent($payment, $this->getUser())
            );
        }else{
            $session->getFlashBag()->add('error', 'Oups, ce lien ne semble pas fonctionner !');
        }
        return $this->redirectToRoute('homepage');
    }

    /**
     * exit app and redirect to resolve
     *
     * @Route("/payment/{id}/orphan_exit_and_back/{code}", name="helloasso_orphan_exit_and_back", methods={"GET"})
     * @Security("has_role('ROLE_USER')")
     */
    public function orphanExitAndConfirm(Request $request,HelloassoPayment $payment,$code){
        $this->get('security.token_storage')->setToken(null);
        $request->getSession()->invalidate();
        return $this->redirectToRoute('helloasso_resolve_orphan',array('id'=>$payment->getId(),'code'=>$code));
    }
}
