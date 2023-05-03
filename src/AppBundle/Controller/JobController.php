<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Job;
use AppBundle\Form\JobType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * Job controller.
 *
 * @Route("admin/job")
 */
class JobController extends Controller
{
    /**
     * Filter form.
     */
    private function filterFormFactory(Request $request): array
    {
        // default values
        $res = [
            "enabled" => 0,
        ];

        // filter creation ----------------------
        $res["form"] = $this->createFormBuilder()
            ->setAction($this->generateUrl('job_list'))
            ->add('enabled', ChoiceType::class, array(
                'label' => 'Poste activé ?',
                'required' => false,
                'choices' => [
                    'activé' => 2,
                    'désactivé' => 1,
                ]
            ))
            ->add('filter', SubmitType::class, array(
                'label' => 'Filtrer',
                'attr' => array('class' => 'btn', 'value' => 'filtrer')
            ))
            ->getForm();

        $res["form"]->handleRequest($request);

        if ($res["form"]->isSubmitted() && $res["form"]->isValid()) {
            $res["enabled"] = $res["form"]->get("enabled")->getData();
        }

        return $res;
    }

    /**
     * Lists all jobs.
     *
     * @Route("/", name="job_list", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function listAction(Request $request)
    {
        $filter = $this->filterFormFactory($request);
        $findByFilter = array();

        if($filter["enabled"] > 0) {
            $findByFilter["enabled"] = $filter["enabled"]-1;
        }

        $em = $this->getDoctrine()->getManager();
        $jobs = $em->getRepository(Job::class)->findBy($findByFilter);

        return $this->render('admin/job/list.html.twig', array(
            'jobs' => $jobs,
            "filter_form" => $filter['form']->createView(),
        ));
    }

    /**
     * add new job.
     *
     * @Route("/new", name="job_new", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newAction(Request $request)
    {
        $session = new Session();

        $job = new Job();
        $job->setEnabled(true);

        $em = $this->getDoctrine()->getManager();

        $form = $this->createForm(JobType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($job);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le nouveau poste a été créé !');
            return $this->redirectToRoute('job_list');
        }

        return $this->render('admin/job/new.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * Edit job.
     *
     * @Route("/{id}/edit", name="job_edit", methods={"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function editAction(Request $request, Job $job)
    {
        $session = new Session();

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(JobType::class, $job);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($job);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le poste a bien été édité !');
            return $this->redirectToRoute('job_list');
        }

        $delete_form = $this->getDeleteForm($job);

        return $this->render('admin/job/edit.html.twig', array(
            'form' => $form->createView(),
            'delete_form' => $delete_form->createView()
        ));
    }

    /**
     * Delete job.
     *
     * @Route("/{id}", name="job_delete", methods={"DELETE"})
     * @Security("has_role('ROLE_SUPER_ADMIN')")
     */
    public function deleteAction(Request $request,Job $job)
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
     * Job shifts widget generator
     *
     * @Route("/widget_generator", name="job_widget_generator", methods={"GET","POST"})
     * @Security("has_role('ROLE_PROCESS_MANAGER')")
     */
    public function widgetGeneratorAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('job', EntityType::class, array(
                'label' => 'Quel poste ?',
                'class' => 'AppBundle:Job',
                'choice_label' => 'name',
                'multiple' => false,
                'required' => true
            ))
            ->add('display_end', CheckboxType::class, array('required' => false, 'label' => 'Afficher l\'heure de fin ?'))
            ->add('display_on_empty', CheckboxType::class, array('required' => false, 'label' => 'Afficher les créneaux vides ?'))
            ->add('title', CheckboxType::class, array('required' => false, 'data' => true, 'label' => 'Afficher le titre du widget ?'))
            ->add('generate', SubmitType::class, array('label' => 'Générer'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {
            $data = $form->getData();

            $widgetQueryString = 'job_id='.$data['job']->getId().'&display_end='.($data['display_end'] ? 1 : 0).'&display_on_empty='.($data['display_on_empty'] ? 1 : 0).'&title='.($data['title'] ? 1 : 0);

            return $this->render('admin/job/widget/generate.html.twig', array(
                'query_string' => $widgetQueryString,
                'form' => $form->createView(),
            ));
        }

        return $this->render('admin/job/widget/generate.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * @param Job $job
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Job $job)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('job_delete', array('id' => $job->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
