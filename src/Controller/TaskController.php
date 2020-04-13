<?php

namespace App\Controller;


use App\Entity\Task;
use App\Form\TaskType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Form;
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
    public function listAction(Request $request, EntityManagerInterface $em)
    {

        $this->denyAccessUnlessGranted('view', new Task());

        $commissions = $em->getRepository('App:Commission')->findAll();
        return $this->render('default/task/list.html.twig', array(
            'commissions' => $commissions,
            'task' => new Task(),
        ));
    }

    /**
     * add new task.
     *
     * @Route("/new", name="task_new")
     * @Method({"GET","POST"})
     */
    public function newAction(Request $request, EntityManagerInterface $em){
        $session = new Session();
        $current_app_user = $this->getUser();

        $task = new Task();

        $this->denyAccessUnlessGranted('create',$task);

        $task->setRegistrar($current_app_user);
        $task->setCreatedAt(new \DateTime('now'));

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $date = $form->get('due_date')->getData();
            $new_date = new \DateTime($date);
            $task->setDueDate($new_date);

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

    }

    /**
     * add new task.
     *
     * @Route("/edit/{id}", name="task_edit")
     * @Method({"GET","POST"})
     */
    public function editAction(Request $request, Task $task, EntityManagerInterface $em)
    {
        $session = new Session();

        $this->denyAccessUnlessGranted('edit',$task);

        $form = $this->createForm(TaskType::class, $task);
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
            'task' => $task,
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($task)->createView()
        ));

    }


    /**
     * task delete
     *
     * @Route("/{id}", name="task_delete")
     * @Method({"DELETE"})
     */
    public function removeAction(Request $request, Task $task, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('delete',$task);
        $session = new Session();
        $form = $this->getDeleteForm($task);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->remove($task);
            $em->flush();
            $session->getFlashBag()->add('success', 'La tache a bien été supprimée !');
        }
        return $this->redirectToRoute('tasks_list');
    }

    /**
     * @param Task $task
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Task $task){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('task_delete', array('id' => $task->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }

    private function getErrorMessages(Form $form) {
        $errors = array();

        foreach ($form->getErrors() as $key => $error) {
            if ($form->isRoot()) {
                $errors['#'][] = $error->getMessage();
            } else {
                $errors[] = $error->getMessage();
            }
        }

        foreach ($form->all() as $child) {
            if (!$child->isValid()) {
                $key = (isset($child->getConfig()->getOptions()['label'])) ? $child->getConfig()->getOptions()['label'] : $child->getName();
                $errors[$key] = $this->getErrorMessages($child);
            }
        }

        return $errors;
    }

}
