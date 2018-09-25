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
     * Lists all codes.
     *
     * @Route("/", name="codes_list")
     * @Method("GET")
     */
    public function listAction(Request $request){
        $session = new Session();

        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        $em = $this->getDoctrine()->getManager();

        if ($current_app_user->hasRole('ROLE_SUPER_ADMIN')){
            $codes = $em->getRepository('AppBundle:Code')->findBy(array(),array('createdAt'=>'DESC'),100);
        }else{
            $codes = $em->getRepository('AppBundle:Code')->findBy(array('closed'=>null),array('createdAt'=>'DESC'),10);
        }

        if (!count($codes)){
            $session->getFlashBag()->add('warning', 'aucun code à lire');
            return $this->redirectToRoute('homepage');

        }

        $this->denyAccessUnlessGranted('view',$codes[0]);

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

        if ($request->get('smartphone') === null){
            return $this->render('default/code/new.html.twig');
        }

        if ($request->get('smartphone') == '0'){
            $value = rand(0,999);
            $code->setValue($value);
        }else{
            $code->setValue(null);
        }

        $code->setClosed(false);
        $code->setCreatedAt(new \DateTime('now'));
        $code->setRegistrar($current_app_user);

        $em = $this->getDoctrine()->getManager();
        $em->persist($code);
        $em->flush();

        $codes = $em->getRepository('AppBundle:Code')->findBy(array('closed'=>null),array('createdAt'=>'DESC'));

        return $this->render('default/code/new.html.twig', array(
            'code' => $code,
            'codes' => $codes,
        ));

    }

    /**
     *
     * @Route("/close/{id}", name="code_close")
     * @Method({"GET"})
     */
    public function editAction(Request $request,Code $code){
        $session = new Session();

        $this->denyAccessUnlessGranted('close',$code);

        $em = $this->getDoctrine()->getManager();

        $code->setClosed(true);

        $em->persist($code);
        $em->flush();

        $session->getFlashBag()->add('success', 'Le code a bien été marqué fermé !');

        return $this->redirectToRoute('codes_list');

    }

    /**
     * close all codes.
     *
     * @Route("/close_all", name="code_done")
     * @Method("GET")
     */
    public function closeAllAction(Request $request){
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        $em = $this->getDoctrine()->getManager();

        $codes = $em->getRepository('AppBundle:Code')->findBy(array('closed'=>null),array('createdAt'=>'DESC'));

        if (!$current_app_user->hasRole('ROLE_SUPER_ADMIN'))
            $this->denyAccessUnlessGranted('view',$codes[0]);

        $em = $this->getDoctrine()->getManager();

        foreach ($codes as $code){
            $code->setClosed(true);
            $em->persist($code);
        }

        $em->flush();

        $session->getFlashBag()->add('success', 'Bien enregistré, merci ! Pense à remettre le code à 0000 si il n\'y a plus de clefs à l\'intérieur');

        return $this->redirectToRoute('homepage');
    }


    /**
     * code delete
     *
     * @Route("/{id}", name="code_delete")
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
            $session->getFlashBag()->add('success', 'Le code a bien été supprimé !');
        }
        return $this->redirectToRoute('codes_list');
    }

    /**
     * @param Code $code
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getDeleteForm(Code $code){
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('code_delete', array('id' => $code->getId())))
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
