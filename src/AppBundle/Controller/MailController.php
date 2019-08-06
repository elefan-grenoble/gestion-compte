<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\User;
use AppBundle\Form\MarkdownEditorType;
use AppBundle\Service\Picture\BasePathPicture;
use AppBundle\Service\SearchUserFormHelper;
use AppBundle\Twig\Extension\AppExtension;
use AppBundle\Twig\Extension\NewsExtension;
use Metadata\Tests\Driver\Fixture\C\SubDir\C;
use Michelf\Markdown;
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
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

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
     * Get non members autocomplete labels
     *
     * @Route("/non_members", name="mail_get_non_members")
     * @Method({"GET"})
     */
    public function nonMembersListAction()
    {
        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository("AppBundle:User")->findNonMember();
        $r = array();
        foreach ($users as $user) {
            $r[] = $user->getUsername().' ['.$user->getEmail().']';
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
        $non_members_users = array();
        $non_members = $this->getDoctrine()->getManager()->getRepository("AppBundle:User")->findNonMember();
        foreach ($non_members as $user){
            $non_members_emails[] = $user;
        }

        $params = array();
        foreach ($request->request as $k => $param) {
            $params[$k] = $param;
        }

        $mailform = $this->getMailForm();
        return $this->render('admin/mail/edit.html.twig', array(
            'form' => $mailform->createView(),
            'to' => $to,
            'non_member' => $non_members_users
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
            $em = $this->getDoctrine()->getManager();
            //beneficiaries
            $to = $mailform->get('to')->getData();
            $chips = json_decode($to);
            $beneficiaries = array();
            foreach ($chips as $chip) {
                $beneficiaries[] = $em->getRepository('AppBundle:Beneficiary')->findFromAutoComplete($chip->tag);
            }
            //end beneficiaries
            //non-member
            $cci = $mailform->get('cci')->getData();
            $chips = json_decode($cci);
            $nonMembers = array();
            $re = '/\[(?<email>.*?)\]/';
            foreach ($chips as $chip) {
                $matches = array();
                preg_match($re, $chip->tag, $matches);
                if (isset($matches['email']))
                    $nonMembers[] = $matches['email'];
            }
            foreach ($nonMembers as $nonMember) {
                /** @var User $user */
                $user = $em->getRepository(User::class)->findOneBy(array('email'=>$nonMember));
                if (is_object($user)) {
                    $fake_beneficiary = new Beneficiary();
                    $fake_beneficiary->setUser($user);
                    $fake_beneficiary->setFirstname($user->getUsername());
                    $fake_beneficiary->setLastname(' ');
                    $beneficiaries[] = $fake_beneficiary;
                }
            }
            //en non-member

            $nb = 0;
            $errored = [];

            $mailerService = $this->get('mailer_service');
            $from_email = $mailform->get('from')->getData();
            if (in_array($from_email, $mailerService->getAllowedEmails())) {
                $from = array($from_email => array_search($from_email, $mailerService->getAllowedEmails()));
            } else {
                //email not listed !
                $session->getFlashBag()->add('error', 'cet email n\'est pas autorisé !');
                return $this->redirectToRoute('mail_edit');
            }
            $contentType = 'text/plain';
            $content = $mailform->get('message')->getData();
            // FIXME Pour envoyer en html
            //$content = Markdown::defaultTransform($content);
            $emailTemplate = $mailform->get('template')->getData();
            if ($emailTemplate) {
                $contentType = 'text/html';
                $content = str_replace('{{template_content}}', $content, $emailTemplate->getContent());
            }

            $template = $this->get('twig')->createTemplate($content);
            foreach ($beneficiaries as $beneficiary) {
                $body = $this->get('twig')->render($template, array('beneficiary' => $beneficiary));
                try {
                    $message = (new \Swift_Message($mailform->get('subject')->getData()))
                        ->setFrom($from)
                        ->setTo([$beneficiary->getEmail() => $beneficiary->getFirstname() . ' ' . $beneficiary->getLastname()])
                        ->addPart(
                            $body,
                            $contentType
                        );
                    $mailer->send($message);
                    $nb++;
                } catch (\Swift_RfcComplianceException $exception) {
                    $errored[] = $beneficiary->getEmail();
                }
            }
            if ($nb > 1) {
                $session->getFlashBag()->add('success', $nb . ' messages envoyés');
                if (!empty($errored)) {
                    $session->getFlashBag()->add('warning', 'Impossible d\'envoyer à : ' . implode(', ', $errored));
                }
            } else {
                $session->getFlashBag()->add('success', 'message envoyé');
            }
        }
        return $this->redirectToRoute('mail_edit');
    }

    private function getMailForm()
    {
        $mailerService = $this->get('mailer_service');
        $mailform = $this->createFormBuilder()
            ->setAction($this->generateUrl('mail_send'))
            ->setMethod('POST')
            ->add('from', ChoiceType::class, array('label' => 'Depuis', 'required' => false, 'choices' => $mailerService->getAllowedEmails()))
            ->add('to', HiddenType::class, array('label' => 'Destinataires', 'required' => true))
            ->add('cci', HiddenType::class, array('label' => 'Non-membres', 'required' => false))
            ->add('template', EntityType::class, array(
                'class' => 'AppBundle:EmailTemplate',
                'placeholder' => '',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => false,
                'label' => 'Modèle'
            ))
            ->add('subject', TextType::class, array('label' => 'Sujet', 'required' => true))
            ->add('message', MarkdownEditorType::class, array('label' => 'Message', 'required' => true, 'attr' => array('class' => 'materialize-textarea')))
            ->getForm();
        return $mailform;
    }
}
