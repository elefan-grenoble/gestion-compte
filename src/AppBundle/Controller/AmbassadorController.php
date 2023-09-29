<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Membership;
use AppBundle\Entity\Note;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Form\NoteType;
use AppBundle\Service\SearchUserFormHelper;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * Ambassador controller.
 *
 * @Route("ambassador")
 */
class AmbassadorController extends Controller
{
    private $timeAfterWhichMembersAreLateWithShifts;
    private $registrationEveryCivilYear;

    public function __construct($timeAfterWhichMembersAreLateWithShifts, $registrationEveryCivilYear)
    {
        $this->timeAfterWhichMembersAreLateWithShifts = $timeAfterWhichMembersAreLateWithShifts;
        $this->registrationEveryCivilYear = $registrationEveryCivilYear;
    }

    /**
     * List all members without a registration.
     *
     * @Route("/noregistration", name="ambassador_noregistration_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_VIEWER')")
     * @param request $request , searchuserformhelper $formhelper
     * @return response
     */
    public function memberNoRegistrationAction(Request $request, SearchUserFormHelper $formHelper)
    {
        $defaults = [
            'sort' => 'r.date',
            'dir' => 'DESC',
            'withdrawn' => 1,
            'registration' => 1,
        ];
        $disabledFields = ['withdrawn', 'registration', 'lastregistrationdatelt', 'lastregistrationdategt'];

        $form = $formHelper->createMemberNoRegistrationFilterForm($this->createFormBuilder(), $defaults, $disabledFields);
        $form->handleRequest($request);

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager(), 'noregistration');
        $qb = $formHelper->processSearchFormAmbassadorData($form, $qb);
        $qb = $qb->andWhere('r.date IS NULL');

        $sort = $form->get('sort')->getData();
        $order = $form->get('dir')->getData();
        $currentPage = $form->get('page')->getData();

        $limitPerPage = 25;
        $qb = $qb->orderBy($sort, $order);
        $paginator = new Paginator($qb);
        $resultCount = count($paginator);
        $pageCount = ($resultCount == 0) ? 1 : ceil($resultCount / $limitPerPage);
        $currentPage = ($currentPage > $pageCount) ? $pageCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage-1)) // set the offset
            ->setMaxResults($limitPerPage); // set the limit

        return $this->render('ambassador/phone/list.html.twig', array(
            'reason' => "adhésion",
            'members' => $paginator,
            'form' => $form->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount
        ));
    }

    /**
     * List all members with a registration date older than one year.
     *
     * @Route("/lateregistration", name="ambassador_lateregistration_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_VIEWER')")
     * @param request $request , searchuserformhelper $formhelper
     * @return response
     */
    public function memberLateRegistrationAction(Request $request, SearchUserFormHelper $formHelper)
    {
        if ($this->registrationEveryCivilYear) {
            $endLastRegistration = new \DateTime('last day of December last year');
        } else {
            $endLastRegistration = new \DateTime('last year');
        }
        $endLastRegistration->setTime(0,0);

        $defaults = [
            'sort' => 'r.date',
            'dir' => 'DESC',
            'withdrawn' => 1,
            'lastregistrationdatelt' => $endLastRegistration,
            'registration' => 2,
        ];
        $disabledFields = ['withdrawn', 'lastregistrationdatelt', 'registration'];

        $form = $formHelper->createMemberLateRegistrationFilterForm($this->createFormBuilder(), $defaults, $disabledFields);
        $form->handleRequest($request);

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager(), 'lateregistration');
        $qb = $formHelper->processSearchFormAmbassadorData($form, $qb);
        $qb = $qb->andWhere('r.date < :lastregistrationdatelt')
            ->setParameter('lastregistrationdatelt', $defaults['lastregistrationdatelt']->format('Y-m-d'));

        $sort = $form->get('sort')->getData();
        $order = $form->get('dir')->getData();
        $currentPage = $form->get('page')->getData();

        $limitPerPage = 25;
        $qb = $qb->orderBy($sort, $order);
        $paginator = new Paginator($qb);
        $resultCount = count($paginator);
        $pageCount = ($resultCount == 0) ? 1 : ceil($resultCount / $limitPerPage);
        $currentPage = ($currentPage > $pageCount) ? $pageCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage-1)) // set the offset
            ->setMaxResults($limitPerPage); // set the limit

        return $this->render('ambassador/phone/list.html.twig', array(
            'reason' => "de ré-adhésion",
            'members' => $paginator,
            'form' => $form->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount
        ));
    }

    /**
     * List all members with negative shift time logs.
     *
     * @Route("/shifttimelog", name="ambassador_shifttimelog_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_MANAGER')")
     * @param request $request , searchuserformhelper $formhelper
     * @return response
     */
    public function memberShiftTimeLogAction(Request $request, SearchUserFormHelper $formHelper)
    {
        $defaults = [
            'sort' => 'time',
            'dir' => 'ASC',
            'withdrawn' => 1,
            'frozen' => 1,
            'compteurlt' => 0,
            'registration' => 2,
        ];
        $disabledFields = ['withdrawn', 'registration'];

        $form = $formHelper->createMemberShiftTimeLogFilterForm($this->createFormBuilder(), $defaults, $disabledFields);
        $form->handleRequest($request);

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager(), 'shifttimelog');
        $qb = $formHelper->processSearchFormAmbassadorData($form, $qb);

        $sort = $form->get('sort')->getData();
        $order = $form->get('dir')->getData();
        $currentPage = $form->get('page')->getData();

        $limitPerPage = 25;
        $qb = $qb->orderBy($sort, $order);
        $paginator = new Paginator($qb);
        $resultCount = count($paginator);
        $pageCount = ($resultCount == 0) ? 1 : ceil($resultCount / $limitPerPage);
        $currentPage = ($currentPage > $pageCount) ? $pageCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage-1)) // set the offset
            ->setMaxResults($limitPerPage); // set the limit

        return $this->render('ambassador/phone/list.html.twig', array(
            'reason' => "de créneaux",
            'members' => $paginator,
            'form' => $form->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount
        ));
    }

    /**
     * display a member phones.
     *
     * @Route("/phone/{member_number}", name="ambassador_phone_show", methods={"GET"})
     * @param Membership $member
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function showAction(Membership $member)
    {
        return $this->redirectToRoute('member_show', array('member_number'=>$member->getMemberNumber()));
    }

    /**
     * Creates a form to delete a note entity.
     *
     * @param Note $note the note entity
     *
     * @return \Symfony\Component\Form\FormInterface The form
     */
    private function createNoteDeleteForm(Note $note)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('note_delete', array('id' => $note->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     *
     * @Route("/note/{member_number}", name="ambassador_new_note", methods={"POST"})
     * @param Membership $member
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function newNoteAction(Membership $member, Request $request)
    {
        $this->denyAccessUnlessGranted('annotate', $member);
        $session = new Session();
        $note = new Note();
        $form = $this->createForm(NoteType::class, $note);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $note->setSubject($member);
            $note->setAuthor($this->get('security.token_storage')->getToken()->getUser());

            $em = $this->getDoctrine()->getManager();
            $em->persist($note);
            $em->flush();

            $session->getFlashBag()->add('success','La note a bien été ajoutée');
        } else {
            $session->getFlashBag()->add('error', 'Impossible d\'ajouter une note');
        }

        return $this->redirectToRoute("ambassador_phone_show", array('member_number'=>$member->getMemberNumber()));
    }
}
