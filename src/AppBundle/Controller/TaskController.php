<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Task;
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
 * @Route("tasks")
 */
class TaskController extends Controller
{

    /**
     * Lists all tasks.
     *
     * @Route("/", name="tasks_list")
     * @Method("GET")
     */
    public function listAction(Request $request){
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        if (!$current_app_user->isRegistrar($request->getClientIp())) {
            throw $this->createAccessDeniedException();
        }
        $securityContext = $this->container->get('security.authorization_checker');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $em = $this->getDoctrine()->getManager();
            $commissions = $em->getRepository('AppBundle:Commission')->findAll();
            return $this->render('default/task/list.html.twig', array(
                'commissions' => $commissions,
                'ip' => $request->getClientIp()
            ));
        }else{
            return $this->redirectToRoute('fos_user_security_login');
        }

    }

    /**
     * add new task.
     *
     * @Route("/new", name="task_new")
     * @Method({"GET","POST"})
     */
    public function newAction(Request $request){
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        $securityContext = $this->container->get('security.authorization_checker');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $task = new Task();

            $em = $this->getDoctrine()->getManager();

            $task->setRegistrar($current_app_user);
            $task->setCreatedAt(new \DateTime('now'));

            $form = $this->createForm('AppBundle\Form\TaskType', $task);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $date = $form->get('due_date')->getData();
                $new_date = new \DateTime($date);
                $task->setDueDate($new_date);

                $owners = $task->getOwners();
                foreach ($owners as $beneficiary){
                    $beneficiary->addTask($task);
                    $em->persist($beneficiary);
                }

                $em->persist($task);
                $em->flush();

                $session->getFlashBag()->add('success', 'La nouvelle tache a bien été créée !');

                return $this->redirectToRoute('task_edit',array('id'=>$task->getId()));

            }elseif ($form->isSubmitted()){
                foreach ($this->getErrorMessages($form) as $key => $errors){
                    foreach ($errors as $error)
                        $session->getFlashBag()->add('error', $key." : ".$error);
                }
            }
            return $this->render('default/task/new.html.twig', array(
                'form' => $form->createView()
            ));

        }else{
            return $this->redirectToRoute('fos_user_security_login');
        }

    }

    /**
     * add new task.
     *
     * @Route("/edit/{id}", name="task_edit")
     * @Method({"GET","POST"})
     */
    public function editAction(Request $request,Task $task){
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        if (!$task->canBeEditedBy($current_app_user)) {
            throw $this->createAccessDeniedException();
        }
        $securityContext = $this->container->get('security.authorization_checker');
        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {

            $em = $this->getDoctrine()->getManager();
            $form = $this->createForm('AppBundle\Form\TaskType', $task);
            $form->get('due_date')->setData($task->getDueDate()->format('Y-m-d'));
            $form->get('created_at')->setData($task->getCreatedAt()->format('Y-m-d'));

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $date = $form->get('due_date')->getData();
                $new_date = new \DateTime($date);
                $task->setDueDate($new_date);

                $date = $form->get('created_at')->getData();
                $new_date = new \DateTime($date);
                $task->setCreatedAt($new_date);

                $owners = $task->getOwners();
                foreach ($owners as $beneficiary){
                    if (! in_array($task,$beneficiary->getTasks()->toArray()))
                        $beneficiary->addTask($task);
                    $em->persist($beneficiary);
                }

                $em->persist($task);
                $em->flush();

                $session->getFlashBag()->add('success', 'La tache a bien été éditée !');

                return $this->redirectToRoute('tasks_list');

            }elseif ($form->isSubmitted()){
                foreach ($this->getErrorMessages($form) as $key => $errors){
                    foreach ($errors as $error)
                        $session->getFlashBag()->add('error', $key." : ".$error);
                }
            }
            return $this->render('default/task/edit.html.twig', array(
                'form' => $form->createView()
            ));

        }else{
            return $this->redirectToRoute('fos_user_security_login');
        }
    }

}
