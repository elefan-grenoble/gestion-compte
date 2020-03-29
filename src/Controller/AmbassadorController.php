<?php

namespace App\Controller;


use App\Entity\Membership;
use App\Entity\Note;
use App\Entity\Task;
use App\Entity\User;
use App\Form\NoteType;
use App\Service\SearchUserFormHelper;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * Task controller.
 *
 * @Route("ambassador")
 */
class AmbassadorController extends Controller
{
    private $timeAfterWhichMembersAreLateWithShifts;

    public function __construct($timeAfterWhichMembersAreLateWithShifts)
    {
        $this->timeAfterWhichMembersAreLateWithShifts = $timeAfterWhichMembersAreLateWithShifts;
    }

    /**
     * Lists all users with a registration date older than one year.
     *
     * @Route("/membership", name="ambassador_membership_list")
     * @Method({"GET","POST"})
     */
    public function membershipAction(Request $request, SearchUserFormHelper $formHelper)
    {

        $this->denyAccessUnlessGranted('view', $this->get('security.token_storage')->getToken()->getUser());

        $form = $formHelper->getSearchForm($this->createFormBuilder(), $request->getQueryString(), true);
        $form->handleRequest($request);

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager());
        $qb = $qb->leftJoin("o.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
            ->where('lr.id IS NULL') //registration is the last one registere
            ->leftJoin("o.timeLogs", "c")->addSelect("c")
            ->addSelect("(SELECT SUM(ti.time) FROM AppBundle\Entity\TimeLog ti WHERE ti.membership = o.id) AS HIDDEN time");

        $page = $request->get('page');
        if (!intval($page))
            $page = 1;
        $order = 'DESC';
        $sort = 'r.date';

        $session = new Session();

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('page')->getData() > 0) {
                $page = $form->get('page')->getData();
            }
            if ($form->get('sort')->getData()) {
                $sort = $form->get('sort')->getData();
            }
            if ($form->get('dir')->getData()) {
                $order = $form->get('dir')->getData();
            }
            $formHelper->processSearchFormAmbassadorData($form, $qb, $session, "membership");
        }else{
            $lastYear = new \DateTime('last year');
            if (!$form->isSubmitted()) {
                $form->get('sort')->setData($sort);
                $form->get('dir')->setData($order);
                $form->get('lastregistrationdatelt')->setData($lastYear->format('Y-m-d'));
                $form->get('withdrawn')->setData(1);
            }
            $qb = $qb->andWhere('o.withdrawn = 0');
            $qb = $qb->andWhere('r.date < :lastregistrationdatelt')
                    ->setParameter('lastregistrationdatelt', $lastYear->format('Y-m-d'));
        }

        $limit = 25;
        $qb2 = clone $qb;
        $max = $qb2->select('count(DISTINCT o.id)')->getQuery()->getSingleScalarResult();
        $nb_of_pages = intval($max/$limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;

        $qb = $qb->orderBy($sort, $order);
        $qb = $qb->setFirstResult( ($page - 1)*$limit )->setMaxResults( $limit );
        $members = new Paginator($qb->getQuery());

        return $this->render('ambassador/phone/list.html.twig', array(
            'reason' => "d'adhésion",
            'path' => 'ambassador_membership_list',
            'members' => $members,
            'form' => $form->createView(),
            'nb_of_result' => $max,
            'page'=>$page,
            'nb_of_pages'=>$nb_of_pages
        ));

    }

    /**
     * Lists all users with shift time logs older than 9 hours.
     *
     * @param request $request , searchuserformhelper $formhelper
     * @return response
     * @Route("/shifttimelog", name="ambassador_shifttimelog_list")
     * @Method({"GET","POST"})
     */
    public function shiftTimeLogAction(Request $request, SearchUserFormHelper $formHelper)
    {

        $this->denyAccessUnlessGranted('view', $this->get('security.token_storage')->getToken()->getUser());

        $form = $formHelper->getSearchForm($this->createFormBuilder(), $request->getQueryString(), true);
        $form->handleRequest($request);

        $action = $form->get('action')->getData();

        $qb = $formHelper->initSearchQuery($this->getDoctrine()->getManager());
        $qb = $qb->leftJoin("o.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
            ->where('lr.id IS NULL') //registration is the last one registere
            ->leftJoin("o.timeLogs", "c")->addSelect("c")
            ->addSelect("(SELECT SUM(ti.time) FROM AppBundle\Entity\TimeLog ti WHERE ti.membership = o.id) AS HIDDEN time");

        $page = $request->get('page');
        if (!intval($page))
            $page = 1;
        $order = 'DESC';
        $sort = 'time';

        $session = new Session();

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('page')->getData() > 0) {
                $page = $form->get('page')->getData();
            }
            if ($form->get('sort')->getData()) {
                $sort = $form->get('sort')->getData();
            }
            if ($form->get('dir')->getData()) {
                $order = $form->get('dir')->getData();
            }
            $formHelper->processSearchFormAmbassadorData($form, $qb, $session, "shifttimelog");
        }else{
            if (!$form->isSubmitted()) {
                $form->get('sort')->setData($sort);
                $form->get('dir')->setData($order);
                $form->get('compteurlt')->setData($this->timeAfterWhichMembersAreLateWithShifts);
                $form->get('withdrawn')->setData(1);
                $form->get('frozen')->setData(1);
            }
            $qb = $qb->andWhere('o.withdrawn = 0');
            $qb = $qb->andWhere('o.frozen = 0');
            $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t.membership) FROM AppBundle\Entity\TimeLog t GROUP BY t.membership HAVING SUM(t.time) < :compteurlt * 60)')
                ->setParameter('compteurlt', $this->timeAfterWhichMembersAreLateWithShifts);
        }

        $limit = 25;
        $qb2 = clone $qb;
        $max = $qb2->select('count(DISTINCT o.id)')->resetDQLPart('groupBy')->getQuery()->getSingleScalarResult();
        $nb_of_pages = intval($max/$limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;

        $qb = $qb->orderBy($sort, $order);
        $qb = $qb->setFirstResult( ($page - 1)*$limit )->setMaxResults( $limit );
        $members = new Paginator($qb->getQuery());

        return $this->render('ambassador/phone/list.html.twig', array(
            'reason' => "de créneaux",
            'path' => 'ambassador_shifttimelog_list',
            'members' => $members,
            'form' => $form->createView(),
            'nb_of_result' => $max,
            'page'=>$page,
            'nb_of_pages'=>$nb_of_pages
        ));

    }

    /**
     * display a member phones.
     *
     * @Route("/phone/{member_number}", name="ambassador_phone_show")
     * @Method("GET")
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
     * @Route("/note/{member_number}", name="ambassador_new_note")
     * @Method("POST")
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
