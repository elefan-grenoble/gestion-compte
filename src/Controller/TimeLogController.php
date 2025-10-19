<?php

namespace App\Controller;


use App\Entity\Membership;
use App\Entity\TimeLog;
use App\Form\TimeLogType;
use App\Service\TimeLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * Time Log controller.
 *
 * @Route("time_log")
 */
class TimeLogController extends AbstractController
{
    private $forbid_own_timelog_new_admin;

    public function __construct(bool $forbid_own_timelog_new_admin)
    {
        $this->forbid_own_timelog_new_admin = $forbid_own_timelog_new_admin;
    }

    /**
     * Create a new log
     *
     * @Route("/{id}/new", name="timelog_new", methods={"GET","POST"})
     * @Security("is_granted('ROLE_SHIFT_MANAGER')")
     * @param Membership $member
     */
    public function newAction(Request $request, Membership $member, TimeLogService $time_log_service)
    {
        $session = new Session();
        $current_user = $this->get('security.token_storage')->getToken()->getUser();

        $timeLog = $time_log_service->initCustomTimeLog($member);

        $form = $this->createForm(TimeLogType::class, $timeLog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member_is_current_user = $current_user->getMembership() == $member;
            // check if user is allowed to create timelog
            if ($member_is_current_user && $this->forbid_own_timelog_new_admin && !$this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $session->getFlashBag()->add('error', 'Vous ne pouvez pas vous rajouter de log de temps.');
                return $this->redirectToShow($member);
            } else {
                $timeLog->setTime($form->get('time')->getData());
                $timeLog->setDescription($form->get('description')->getData());

                $em = $this->getDoctrine()->getManager();
                $em->persist($timeLog);
                $em->flush();

                $session->getFlashBag()->add('success', 'Le log de temps a bien été créé !');
                return $this->redirectToShow($member);
            }
        }

        return $this->redirectToShow($member);
    }

    /**
     * Delete time log
     *
     * @Route("/{id}/timelog_delete/{timelog_id}", name="member_timelog_delete", methods={"DELETE"})
     * @Security("is_granted('ROLE_SUPER_ADMIN')")
     * @param Membership $member
     * @param $timelog_id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function timelogDeleteAction(Membership $member, $timelog_id)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();
        $timeLog = $this->getDoctrine()->getManager()->getRepository('App:TimeLog')->find($timelog_id);
        if ($timeLog->getMembership() === $member) {
            $em->remove($timeLog);
            $em->flush();
            $session->getFlashBag()->add('success', 'Time log supprimé');
        } else {
            $session->getFlashBag()->add('error', $timeLog->getMembership() . '<>' . $member);
            $session->getFlashBag()->add('error', $timeLog->getId());
        }
        return $this->redirectToShow($member);
    }

    private function redirectToShow(Membership $member)
    {
        $securityContext = $this->container->get('security.authorization_checker');
        if (!$securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('homepage');
        }
        $session = new Session();
        if ($this->get('security.authorization_checker')->isGranted('ROLE_USER_MANAGER'))
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber()));
        else
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber(), 'token' => $member->getTmpToken($session->get('token_key') . $this->getUser()->getUsername())));
    }
}
