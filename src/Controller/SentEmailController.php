<?php

namespace App\Controller;

use App\Entity\SentEmail;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * History of the mails sent by the application.
 *
 * @Route("admin/mails/historique")
 */
class SentEmailController extends AbstractController
{
    private const LIMIT_PER_PAGE = 25;

    /**
     * Lists the sent mails, newest first.
     *
     * @Route("/", name="admin_sent_email_index", methods={"GET","POST"})
     *
     * @Security("is_granted('ROLE_USER_MANAGER')")
     */
    public function indexAction(Request $request): Response
    {
        $form = $this->createFilterForm();
        $form->handleRequest($request);

        $criteria = [];
        $page = 1;
        if ($form->isSubmitted() && $form->isValid()) {
            foreach (['from', 'to', 'recipient', 'subject', 'source', 'status'] as $field) {
                $criteria[$field] = $form->get($field)->getData();
            }
            $page = (int) $form->get('page')->getData();
        }

        $repository = $this->getDoctrine()->getRepository(SentEmail::class);
        $qb = $repository->createFilteredQueryBuilder($criteria);

        $paginator = new Paginator($qb, false);
        $resultCount = count($paginator);
        $pageCount = max(1, (int) ceil($resultCount / self::LIMIT_PER_PAGE));
        $currentPage = max(1, min($page, $pageCount));

        $qb->setFirstResult(($currentPage - 1) * self::LIMIT_PER_PAGE)->setMaxResults(self::LIMIT_PER_PAGE);

        $sentEmails = iterator_to_array(new Paginator($qb, false));

        return $this->render('admin/mail/history/index.html.twig', [
            'filter_form' => $form->createView(),
            'sent_emails' => $sentEmails,
            'batches' => $repository->batchSummaries(array_map(function (SentEmail $mail) {
                return $mail->getBatchId();
            }, $sentEmails)),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount,
        ]);
    }

    /**
     * The mails sent together (one per recipient of a group).
     *
     * @Route("/groupe/{batchId}", name="admin_sent_email_batch", methods={"GET"})
     *
     * @Security("is_granted('ROLE_USER_MANAGER')")
     */
    public function batchAction(Request $request, string $batchId): Response
    {
        $repository = $this->getDoctrine()->getRepository(SentEmail::class);
        $summary = $repository->batchSummaries([$batchId])[$batchId] ?? null;
        if (null === $summary) {
            throw $this->createNotFoundException();
        }

        $pageCount = max(1, (int) ceil($summary['total'] / self::LIMIT_PER_PAGE));
        $currentPage = max(1, min($request->query->getInt('page', 1), $pageCount));

        $qb = $repository->createBatchQueryBuilder($batchId)
            ->setFirstResult(($currentPage - 1) * self::LIMIT_PER_PAGE)
            ->setMaxResults(self::LIMIT_PER_PAGE)
        ;
        $sentEmails = iterator_to_array(new Paginator($qb, false));

        return $this->render('admin/mail/history/batch.html.twig', [
            'batch_id' => $batchId,
            'summary' => $summary,
            'sent_emails' => $sentEmails,
            'current_page' => $currentPage,
            'page_count' => $pageCount,
        ]);
    }

    /**
     * Details of a sent mail.
     *
     * @Route("/{id}", name="admin_sent_email_show", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @Security("is_granted('ROLE_USER_MANAGER')")
     */
    public function showAction(SentEmail $sentEmail): Response
    {
        $batchId = $sentEmail->getBatchId();

        return $this->render('admin/mail/history/show.html.twig', [
            'sent_email' => $sentEmail,
            'batch_summary' => $batchId ? ($this->getDoctrine()->getRepository(SentEmail::class)->batchSummaries([$batchId])[$batchId] ?? null) : null,
        ]);
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createFilterForm(): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_sent_email_index'))
            ->add('from', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'label' => 'Du',
                'required' => false,
                'attr' => ['class' => 'datepicker'],
            ])
            ->add('to', DateType::class, [
                'widget' => 'single_text',
                'html5' => false,
                'label' => 'Au (inclus)',
                'required' => false,
                'attr' => ['class' => 'datepicker'],
            ])
            ->add('recipient', TextType::class, [
                'label' => 'Destinataire',
                'required' => false,
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'required' => false,
            ])
            ->add('source', ChoiceType::class, [
                'label' => 'Type',
                'required' => false,
                'placeholder' => 'Tous',
                'choices' => SentEmail::SOURCES,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'État',
                'required' => false,
                'placeholder' => 'Tous',
                'choices' => SentEmail::STATUSES,
            ])
            ->add('page', HiddenType::class, [
                'data' => '1',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Filtrer',
                'attr' => ['class' => 'btn', 'value' => 'filtrer'],
            ])
            ->getForm()
        ;
    }
}
