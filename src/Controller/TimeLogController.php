<?php

namespace App\Controller;


use App\Entity\Membership;
use App\Entity\TimeLog;
use App\Form\TimeLogType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Time Log controller.
 *
 * @Route("time_log")
 */
class TimeLogController extends Controller
{

    /**
     * Delete time log
     *
     * @Route("/{id}/timelog_delete/{timelog_id}", name="member_timelog_delete")
     * @Method({"GET"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @param Membership $member
     * @param $timelog_id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function timelogDeleteAction(Membership $member, $timelog_id, EntityManagerInterface $em)
    {
        $session = new Session();
        $timeLog = $em->getRepository('App:TimeLog')->find($timelog_id);
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

    /**
     * Create a new log
     *
     * @Route("/{id}/new", name="time_log_new")
     * @Method({"GET", "POST"})
     * @Security("has_role('ROLE_SHIFT_MANAGER')")
     * @param Membership $member
     */
    public function newAction(Request $request, Membership $member, EntityManagerInterface $em)
    {
        $session = new Session();
        $timeLog = new TimeLog();

        $form = $this->createForm(TimeLogType::class, $timeLog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $timeLog->setDate(new \DateTime());
            $timeLog->setMembership($member);
            $timeLog->setTime($form->get('time')->getData());
            $timeLog->setDescription($form->get('description')->getData());
            $timeLog->setType(TimeLog::TYPE_CUSTOM);

            $em->persist($timeLog);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le log de temps a bien été créé !');
            return $this->redirectToShow($member);
        }

        return $this->redirectToShow($member);
    }

    private function redirectToShow(Membership $member, AuthorizationCheckerInterface $authorizationChecker)
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('homepage');
        }
        $session = new Session();
        if ($authorizationChecker->isGranted('ROLE_USER_MANAGER'))
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber()));
        else
            return $this->redirectToRoute('member_show', array('member_number' => $member->getMemberNumber(), 'token' => $member->getTmpToken($session->get('token_key') . $this->getUser()->getUsername())));
    }
}
