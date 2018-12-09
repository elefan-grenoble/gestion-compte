<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Job;
use AppBundle\Entity\Task;
use AppBundle\Form\JobType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * Task controller.
 *
 * @Route("admin/job")
 */
class JobController extends Controller
{

    /**
     * Lists all tasks.
     *
     * @Route("/", name="job_list")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function listAction(Request $request){

        $em = $this->getDoctrine()->getManager();
        $jobs = $em->getRepository('AppBundle:Job')->findAll();
        return $this->render('admin/job/list.html.twig', array(
            'jobs' => $jobs
        ));
    }

    /**
     * add new job.
     *
     * @Route("/new", name="job_new")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request){
        $session = new Session();

        $job = new Job();

        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(JobType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($job);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le nouveau poste a été créé !');

            return $this->redirectToRoute('job_list');

        }elseif ($form->isSubmitted()){
            foreach ($this->getErrorMessages($form) as $key => $errors){
                foreach ($errors as $error)
                    $session->getFlashBag()->add('error', $key." : ".$error);
            }
        }
        return $this->render('admin/job/new.html.twig', array(
            'form' => $form->createView()
        ));

    }

    /**
     * add new job.
     *
     * @Route("/edit/{id}", name="job_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request,Job $job){
        $session = new Session();

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(JobType::class, $job);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($job);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le poste a bien été édité !');

            return $this->redirectToRoute('job_list');

        }elseif ($form->isSubmitted()){
            foreach ($this->getErrorMessages($form) as $key => $errors){
                foreach ($errors as $error)
                    $session->getFlashBag()->add('error', $key." : ".$error);
            }
        }
        return $this->render('admin/job/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($job)->createView()
        ));

    }


    /**
     * job delete
     *
     * @Route("/{id}", name="job_delete")
     * @Method({"DELETE"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function removeAction(Request $request,Job $job)
    {
        $session = new Session();
        $form = $this->getDeleteForm($job);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($job);
            $em->flush();
            $session->getFlashBag()->add('success', 'Le poste a bien été supprimée !');
        }
        return $this->redirectToRoute('job_list');
    }

    /**
     * @param Job $job
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Job $job){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('job_delete', array('id' => $job->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

}
