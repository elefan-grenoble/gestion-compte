<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Entity\Shift;
use App\Entity\User;
use App\Form\AutocompleteBeneficiaryCollectionType;
use App\Form\MarkdownEditorType;
use App\Service\MailerService;
use App\Service\SearchUserFormHelper;
use Michelf\Markdown;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Email controller.
 *
 * @Route("admin/mail")
 * @Security("is_granted('ROLE_USER_MANAGER')")
 */
class MailController extends AbstractController
{

    /**
     * Edit a message
     *
     * @Route("/to/{id}", name="mail_edit_one_beneficiary", methods={"GET","POST"})
     */
    public function editActionOneBeneficiary(Request $request, Beneficiary $beneficiary, MailerService $mailer_service)
    {
        $mailform = $this->getMailForm($mailer_service, array($beneficiary));
        $non_members = $this->getNonMemberEmails();
        return $this->render('admin/mail/send.html.twig', array(
            'form' => $mailform->createView(),
            'non_members' => $non_members
        ));
    }

    /**
     * @Route("/to_bucket/{id}", name="mail_bucketshift", methods={"GET","POST"})
     */
    public function mailBucketShift(Request $request, Shift $shift, MailerService $mailer_service)
    {
        if ($shift) {
            $em = $this->getDoctrine()->getManager();
            $shifts = $em->getRepository(Shift::class)->findBy(array('job' => $shift->getJob(), 'start' => $shift->getStart(), 'end' => $shift->getEnd()));
            $beneficiaries = array();
            foreach ($shifts as $shift) {
                if ($shift->getShifter()) {
                    $beneficiaries[] = $shift->getShifter();
                }
            }
            $mailform = $this->getMailForm($mailer_service, $beneficiaries);
            $non_members = $this->getNonMemberEmails();
            return $this->render('admin/mail/send.html.twig', array(
                'form' => $mailform->createView(),
                'non_members' => $non_members
            ));
        }
    }

    /**
     * Edit a message
     *
     * @Route("/", name="mail_edit", methods={"GET","POST"})
     */
    public function editAction(Request $request, SearchUserFormHelper $formHelper, MailerService $mailer_service)
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
        $non_members = $this->getNonMemberEmails();

        $mailform = $this->getMailForm($mailer_service, $to);
        return $this->render('admin/mail/send.html.twig', array(
            'form' => $mailform->createView(),
            'non_members' => $non_members
        ));
    }


    /**
     * Send a message
     *
     * @Route("/send", name="mail_send", methods={"POST"})
     */
    public function sendAction(Request $request, MailerInterface $mailer, MailerService $mailer_service)
    {
        $session = new Session();
        $mailform = $this->getMailForm($mailer_service);
        $mailform->handleRequest($request);
        if ($mailform->isSubmitted() && $mailform->isValid()) {
            $em = $this->getDoctrine()->getManager();
            //beneficiaries
            $beneficiaries = $mailform->get('to')->getData();
            //non-member
            $cci = $mailform->get('cci')->getData();
            $nonMembers = json_decode($cci);
            foreach ($nonMembers as $nonMember) {
                /** @var User $user */
                $user = $em->getRepository(User::class)->findOneBy(array('email' => $nonMember));
                if (is_object($user)) {
                    $fake_beneficiary = new Beneficiary();
                    $fake_beneficiary->setFlying(false);
                    $fake_beneficiary->setUser($user);
                    $fake_beneficiary->setFirstname($user->getUsername());
                    $fake_beneficiary->setLastname(' ');
                    $beneficiaries[] = $fake_beneficiary;
                }
            }
            //en non-member

            $nb = 0;
            $errored = [];

            $from_email = $mailform->get('from')->getData();
            if (in_array($from_email, $mailer_service->getAllowedEmails())) {
                $from_name_and_address = array_search($from_email, $mailer_service->getAllowedEmails());
                $from_name = preg_replace("/<.*>$/", "", $from_name_and_address);
                $from = new Address($from_email, $from_name);
            } else {
                //email not listed !
                $session->getFlashBag()->add('error', 'cet email n\'est pas autorisé !');
                return $this->redirectToRoute('mail_edit');
            }
            $content = $mailform->get('message')->getData();
            $parser = new Markdown;
            $parser->hard_wrap=true;
            $content = $parser->transform($content);
            $emailTemplate = $mailform->get('template')->getData();
            if ($emailTemplate) {
                $content = str_replace('{{template_content}}', $content, $emailTemplate->getContent());
            }

            $template = $this->get('twig')->createTemplate($content);
            foreach ($beneficiaries as $beneficiary) {
                $body = $this->get('twig')->render($template, array('beneficiary' => $beneficiary));
                try {
                    $message = (new Email())
                        ->subject($mailform->get('subject')->getData())
                        ->from($from)
                        ->to(new Address($beneficiary->getEmail(), $beneficiary->getFirstname() . ' ' . $beneficiary->getLastname()))
                        ->html($body);
                    $mailer->send($message);
                    $nb++;
                } catch (TransportExceptionInterface $exception) {
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

    private function getMailForm(MailerService $mailer_service, $to = []) {
        $mailform = $this->createFormBuilder()
            ->setAction($this->generateUrl('mail_send'))
            ->setMethod('POST')
            ->add('from', ChoiceType::class, array(
                'label' => 'Depuis',
                'required' => false,
                'choices' => $mailer_service->getAllowedEmails()
            ))
            ->add('to', AutocompleteBeneficiaryCollectionType::class, [
                'data' => $to,
                'label' => "Destinataire(s)",
            ])
            ->add('cci', HiddenType::class, array('label' => 'Non-membres', 'required' => false))
            ->add('template', EntityType::class, array(
                'class' => 'App:EmailTemplate',
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

    private function getNonMemberEmails() {
        $em = $this->getDoctrine()->getManager();
        $non_members = $em->getRepository("App:User")->findNonMembers(true);
        $list = [];
        foreach ($non_members as $non_member){
            $list[$non_member->getEmail()] = '';
        }
        return $list;
    }
}
