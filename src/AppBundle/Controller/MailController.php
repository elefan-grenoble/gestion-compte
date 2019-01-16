<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Service\SearchUserFormHelper;
use Metadata\Tests\Driver\Fixture\C\SubDir\C;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
 * @Security("has_role('ROLE_USER_MANAGER')")
 */
class MailController extends Controller
{

    /**
     * Get beneficiaries autocomplete labels
     *
     * @Route("/beneficiaries", name="mail_get_beneficiaries")
     * @Method({"GET"})
     */
    public function allBeneficiariesAction()
    {
        $em = $this->getDoctrine()->getManager();
        $beneficiaries = $em->getRepository('AppBundle:Beneficiary')->findAll();
        $r = array();
        foreach ($beneficiaries as $beneficiary) {
            $r[] = $beneficiary->getAutocompleteLabel();
        }
        return $this->json($r);
    }

    /**
     * Edit a message
     *
     * @Route("/to/{id}", name="mail_edit_one_beneficiary")
     * @Method({"GET","POST"})
     */
    public function editActionOneBeneficiary(Request $request, Beneficiary $beneficiary)
    {
        $mailform = $this->getMailForm();
        return $this->render('admin/mail/edit.html.twig', array(
            'form' => $mailform->createView(),
            'to' => array($beneficiary),
        ));
    }

    /**
     * Edit a message
     *
     * @Route("/", name="mail_edit")
     * @Method({"GET","POST"})
     */
    public function editAction(Request $request, SearchUserFormHelper $formHelper)
    {
        $form = $formHelper->getSearchForm($this->createFormBuilder());
        $form->handleRequest($request);
        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager());

        $to = array();
        if ($form->isSubmitted() && $form->isValid()) {
            $qb = $formHelper->processSearchFormData($form, $qb);
            $members = $qb->getQuery()->getResult();
            foreach ($members as $member) {
                foreach ($member->getBeneficiaries() as $beneficiary) {
                    $to[] = $beneficiary;
                }
            }
        }

        $params = array();
        foreach ($request->request as $k => $param) {
            $params[$k] = $param;
        }

        $mailform = $this->getMailForm();
        return $this->render('admin/mail/edit.html.twig', array(
            'form' => $mailform->createView(),
            'to' => $to,
        ));
    }


    /**
     * Send a message
     *
     * @Route("/send", name="mail_send")
     * @Method({"POST"})
     */
    public function sendAction(Request $request, \Swift_Mailer $mailer)
    {
        $session = new Session();
        $mailform = $this->getMailForm();
        $mailform->handleRequest($request);
        if ($mailform->isSubmitted() && $mailform->isValid()) {
            $to = $mailform->get('to')->getData();
            $chips = json_decode($to);
            $beneficiaries = array();

            $em = $this->getDoctrine()->getManager();

            foreach ($chips as $chip) {
                $beneficiaries[] = $em->getRepository('AppBundle:Beneficiary')->findFromAutoComplete($chip->tag);
            }

            $nb = 0;

            $mailerService = $this->get('mailer_service');

            $from_email = $mailform->get('from')->getData();
            $from = '';
            if (in_array($from_email, $mailerService->getAllowedEmails())) {
                $from = array($from_email => array_search($from_email, $mailerService->getAllowedEmails()));
            } else {
                //email not listed !
                $session->getFlashBag()->add('error', 'cet email n\'est pas autorisé !');
                return $this->redirectToRoute('mail_edit');
            }

            foreach ($beneficiaries as $beneficiary) {
                $template = $this->get('twig')->createTemplate($mailform->get('message')->getData());
                $body = $template->render(array('beneficiary' => $beneficiary));
                $message = (new \Swift_Message($mailform->get('subject')->getData()))
                    ->setFrom($from)
                    ->setTo([$beneficiary->getEmail() => $beneficiary->getFirstname() . ' ' . $beneficiary->getLastname()])
                    ->addPart(
                        $body,
                        'text/plain'
                    );
                $mailer->send($message);
                $nb++;
            }
            if ($nb > 1)
                $session->getFlashBag()->add('success', $nb . ' messages envoyés');
            else
                $session->getFlashBag()->add('success', 'message envoyé');
        }
        return $this->redirectToRoute('mail_edit');
    }

    private function getMailForm()
    {
        $mailerService = $this->get('mailer_service');
        $mailform = $this->createFormBuilder()
            ->setAction($this->generateUrl('mail_send'))
            ->setMethod('POST')
            ->add('from', ChoiceType::class, array('label' => 'depuis', 'required' => false, 'choices' => $mailerService->getAllowedEmails()))
            ->add('to', HiddenType::class, array('label' => 'à', 'required' => true))
            ->add('subject', TextType::class, array('label' => 'sujet', 'required' => true))
            ->add('message', TextareaType::class, array('label' => 'message', 'required' => true, 'attr' => array('class' => 'materialize-textarea')))
            ->getForm();
        return $mailform;
    }
}