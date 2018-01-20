<?php

namespace AppBundle\Controller;

use Metadata\Tests\Driver\Fixture\C\SubDir\C;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Email controller.
 *
 * @Route("admin/mail")
 * @Security("has_role('ROLE_ADMIN')")
 */
class MailController extends Controller
{
    /**
     * Edit a message
     *
     * @Route("/edit", name="mail_edit")
     * @Method({"GET","POST"})
     */
    public function editAction(Request $request){
        $adminController = new AdminController();
        $adminController->setContainer($this->container);
        $form = $adminController->getSearchForm();
        $form->handleRequest($request);
        $qb = $adminController->initSearchQuery();
        if ($form->isSubmitted() && $form->isValid()) {
            $qb = $adminController->processSearchFormData($form,$qb);
            $to = $qb->getQuery()->getResult();
        }else{
            $to = array();
        }
        $mailform = $this->getMailForm();
        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository('AppBundle:User')->findAll();
        return $this->render('admin/mail/edit.html.twig', array(
            'form' => $mailform->createView(),
            'users' => $users,
            'to' => $to,
        ));
    }

    /**
     * Send a message
     *
     * @Route("/send", name="mail_send")
     * @Method({"POST"})
     */
    public function sendAction(Request $request, \Swift_Mailer $mailer){
        $session = new Session();
        $mailform = $this->getMailForm();
        $mailform->handleRequest($request);
        if ($mailform->isSubmitted() && $mailform->isValid()) {
            $to = $mailform->get('to')->getData();
            $chips = json_decode($to);
            $members_numbers = array();
            foreach ($chips as $chip){
                $re = '/#([0-9]+)([a-z\s])*/i';
                $tag = $chip->tag;
                preg_match_all($re, $tag, $matches, PREG_SET_ORDER, 0);
                if (count($matches)){
                    $members_numbers[] = $matches[0][1];
                }
            }
            $em = $this->getDoctrine()->getManager();
            $users = $em->getRepository('AppBundle:User')->findBy(array('member_number'=>$members_numbers));
            $nb = 0;
            foreach ($users as $user){
                $template = $this->get('twig')->createTemplate($mailform->get('message')->getData());
                $body = $template->render(array('user' => $user));
                $message = (new \Swift_Message($mailform->get('subject')->getData()))
                    ->setFrom('membres@lelefan.org')
                    ->setTo($user->getEmail())
                    ->addPart(
                        $body,
                        'text/plain'
                    );
                $mailer->send($message);
                $nb++;
            }
            if ($nb>1)
                $session->getFlashBag()->add('success',$nb.' messages envoyés');
            else
                $session->getFlashBag()->add('success','message envoyé');
        }
        return $this->redirectToRoute('mail_edit');
    }

    /**
     * Edit a message
     *
     * @Route("/users", name="mail_get_users")
     * @Method({"GET"})
     */
    public function allUsersAction(){
        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository('AppBundle:User')->findAll();
        $r = array();
        foreach ($users as $user){
            $r[] = $user->getAutocompleteLabel();
        }
        return $this->json($r);
    }

    private function getMailForm(){
        $mailform = $this->createFormBuilder()
            ->setAction($this->generateUrl('mail_send'))
            ->setMethod('POST')
            ->add('to', HiddenType::class, array('label' => 'à','required' => true))
            ->add('subject', TextType::class, array('label' => 'sujet','required' => true))
            ->add('message', TextareaType::class, array('label' => 'message','required' => true,'attr'=>array('class'=>'materialize-textarea')))
            ->getForm();
        return $mailform;
    }
}