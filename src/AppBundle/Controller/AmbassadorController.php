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
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * Ambassador controller
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
     * List all members without a registration
     *
     * @Route("/noregistration", name="ambassador_noregistration_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_VIEWER')")
     * @param request $request , searchuserformhelper $formhelper
     * @return response
     */
    public function memberNoRegistrationAction(Request $request, SearchUserFormHelper $formHelper)
    {
        $defaults = [
            'withdrawn' => 1,
            'registration' => 1,
            'sort' => 'r.date',
            'dir' => 'DESC'
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
            'reason' => "Liste des membres sans adhésion",
            'members' => $paginator,
            'form' => $form->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount
        ));
    }

    /**
     * List all members with a registration date older than one year
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
            'withdrawn' => 1,
            'registration' => 2,
            'lastregistrationdatelt' => $endLastRegistration,
            'sort' => 'r.date',
            'dir' => 'DESC'
        ];
        $disabledFields = ['withdrawn', 'registration', 'lastregistrationdatelt'];

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
            'reason' => "Liste des membres en retard de ré-adhésion",
            'members' => $paginator,
            'form' => $form->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount
        ));
    }

    /**
     * List all members with negative shift time count
     *
     * @Route("/shifttimelog", name="ambassador_shifttimelog_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_MANAGER')")
     * @param request $request , searchuserformhelper $formhelper
     * @return response
     */
    public function memberShiftTimeLogAction(Request $request, SearchUserFormHelper $formHelper)
    {
        $action = $request->request->get("form")["csv"] ?? null;

        $defaults = [
            'withdrawn' => 1,
            'frozen' => 1,
            'registration' => 2,
            'compteurlt' => 0,
            'sort' => 'time',
            'dir' => 'ASC'
        ];
        $disabledFields = ['withdrawn', 'registration'];

        $form = $formHelper->createMemberShiftTimeLogFilterForm($this->createFormBuilder(), $defaults, $disabledFields);
        $form->handleRequest($request);

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager(), 'shifttimelog');
        $qb = $formHelper->processSearchFormAmbassadorData($form, $qb);

        $sort = $form->get('sort')->getData();
        $order = $form->get('dir')->getData();
        $currentPage = $form->get('page')->getData();

        $qb = $qb->orderBy($sort, $order);

        // Export CSV
        if ($action == "csv") {
            $members = $qb->getQuery()->getResult();

            $data = array_map(function($member) {
                $names = $member->getBeneficiaries()->map(function($b) { return $b->getFirstname() . " " . $b->getLastname(); });
                return [
                    $member->getMemberNumber(),
                    join($names->toArray(), " & "),
                    $member->getLastRegistration()->getDate()->format("d/m/Y"),
                    $member->getShiftTimeCount() / 60
                ];
            }, $members);

            $response = new StreamedResponse();
            $response->setCallback(function () use ($data) {
                $handle = fopen('php://output', 'wb');
                foreach ($data as $row) {
                    fputcsv($handle, $row, ',');
                }
                fclose($handle);
            });

            $response->setStatusCode(Response::HTTP_OK);
            $response->headers->set('Content-Encoding', 'UTF-8');
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="relances_créneaux_' . date('dmyhis') . '.csv"');

            return $response;
        }

        $limitPerPage = 25;
        $paginator = new Paginator($qb);
        $resultCount = count($paginator);
        $pageCount = ($resultCount == 0) ? 1 : ceil($resultCount / $limitPerPage);
        $currentPage = ($currentPage > $pageCount) ? $pageCount : $currentPage;

        $paginator
            ->getQuery()
            ->setFirstResult($limitPerPage * ($currentPage-1)) // set the offset
            ->setMaxResults($limitPerPage); // set the limit

        return $this->render('ambassador/phone/list.html.twig', array(
            'reason' => "Liste des membres en retard de créneaux",
            'members' => $paginator,
            'form' => $form->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount
        ));
    }

    /**
     * List all beneficiaries "fixe" without periodposition
     * Useful for use_fly_and_fixed and fly_and_fixed_entity_flying == 'Beneficiary'
     *
     * @Route("/beneficiary_fixe_without_periodposition", name="ambassador_beneficiary_fixe_without_periodposition_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_USER_MANAGER')")
     * @param request $request , searchuserformhelper $formhelper
     * @return response
     */
    public function beneficiaryFixeWithoutPeriodPosition(Request $request, SearchUserFormHelper $formHelper)
    {
        $defaults = [
            'withdrawn' => 1,
            'frozen' => 1,
            'registration' => 2,
            'flying' => 1,
            'has_period_position' => 1,
            'sort' => 'm.member_number',
            'dir' => 'ASC'
        ];
        $disabledFields = ['withdrawn', 'registration', 'flying', 'has_period_position'];

        $form = $formHelper->createBeneficiaryFixeWithoutPeriodPositionForm($this->createFormBuilder(), $defaults, $disabledFields);
        $form->handleRequest($request);

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager(), 'fixe_without_periodposition');
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
            'reason' => "Liste des bénéficiaires fixes sans poste fixe",
            'members' => $paginator,
            'form' => $form->createView(),
            'result_count' => $resultCount,
            'current_page' => $currentPage,
            'page_count' => $pageCount
        ));
    }

    /**
     * Display a member phones
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
     * Creates a form to delete a note entity
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
     * Create a note
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
