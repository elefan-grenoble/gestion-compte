<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Code;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * Code controller.
 *
 * @Route("codes")
 */
class CodeController extends Controller
{

    /**
     * Lists all tasks.
     *
     * @Route("/", name="codes_list")
     * @Method("GET")
     */
    public function listAction(Request $request){

        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        $this->denyAccessUnlessGranted('view',new Code());

        $em = $this->getDoctrine()->getManager();

        if ($current_app_user->hasRole('ROLE_SUPER_ADMIN')){
            $codes = $em->getRepository('AppBundle:Code')->findAll();
        }else{
            $codes = $em->getRepository('AppBundle:Code')->findAll();
        }

        return $this->render('default/code/list.html.twig', array(
            'codes' => $codes,
            'code' => new Code(),
        ));
    }

    /**
     * add new code.
     *
     * @Route("/new", name="code_new")
     * @Method({"GET","POST"})
     */
    public function newAction(Request $request){
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        $code = new Code();

        $this->denyAccessUnlessGranted('create',$code);

        $em = $this->getDoctrine()->getManager();

        $code->setRegistrar($current_app_user);
        $code->setCreatedAt(new \DateTime('now'));

        $form = $this->createForm('AppBundle\Form\CodeType', $code);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($code);
            $em->flush();

            $session->getFlashBag()->add('success', 'Le nouveau code a bien été créé !');

            return $this->redirectToRoute('homepage');

        }elseif ($form->isSubmitted()){
            foreach ($this->getErrorMessages($form) as $key => $errors){
                foreach ($errors as $error)
                    $session->getFlashBag()->add('error', $key." : ".$error);
            }
        }
        return $this->render('default/code/new.html.twig', array(
            'form' => $form->createView()
        ));

    }

    /**
     * add new task.
     *
     * @Route("/edit/{id}", name="task_edit")
     * @Method({"GET","POST"})
     */
    public function editAction(Request $request,Code $code){
        $session = new Session();

        $this->denyAccessUnlessGranted('edit',$code);

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm('AppBundle\Form\CodeType', $code);
        $form->get('due_date')->setData($code->getDueDate()->format('Y-m-d'));
        $form->get('created_at')->setData($code->getCreatedAt()->format('Y-m-d'));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $date = $form->get('due_date')->getData();
            $new_date = new \DateTime($date);
            $code->setDueDate($new_date);

            $date = $form->get('created_at')->getData();
            $new_date = new \DateTime($date);
            $code->setCreatedAt($new_date);

            $em->persist($code);
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
            'task' => $code,
            'form' => $form->createView(),
            'delete_form' => $this->getDeleteForm($code)->createView()
        ));

    }


    /**
     * task delete
     *
     * @Route("/{id}", name="task_delete")
     * @Method({"DELETE"})
     */
    public function removeAction(Request $request,Code $code)
    {
        $this->denyAccessUnlessGranted('delete',$code);
        $session = new Session();
        $form = $this->getDeleteForm($code);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($code);
            $em->flush();
            $session->getFlashBag()->add('success', 'La tache a bien été supprimée !');
        }
        return $this->redirectToRoute('tasks_list');
    }

    /**
     * @param Code $code
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Code $code){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('task_delete', array('id' => $code->getId())))
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
