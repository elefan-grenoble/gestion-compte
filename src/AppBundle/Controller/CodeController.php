<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Code;
use AppBundle\Event\CodeNewEvent;
use AppBundle\Security\CodeVoter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
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
     * @Security("has_role('ROLE_USER')")
     */
    public function listAction(Request $request){
        $session = new Session();

        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        $logger = $this->get('logger');
        $logger->info('CODE : codes_list',array('username'=>$current_app_user->getUsername()));

        $em = $this->getDoctrine()->getManager();

        if ($current_app_user->hasRole('ROLE_SUPER_ADMIN')){
            $codes = $em->getRepository('AppBundle:Code')->findBy(array(),array('createdAt'=>'DESC'),100);
        }else{
            $codes = $em->getRepository('AppBundle:Code')->findBy(array('closed'=>0),array('createdAt'=>'DESC'),10);
        }

        if (!count($codes)){
            $session->getFlashBag()->add('warning', 'aucun code à lire');
            return $this->redirectToRoute('homepage');
        }

        $this->denyAccessUnlessGranted('view',$codes[0]);

        return $this->render('default/code/list.html.twig', array(
            'codes' => $codes
        ));
    }

    /**
     * add new code.
     *
     * @Route("/new", name="code_new")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function newAction(Request $request){
        $session = new Session();
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();

        $em = $this->getDoctrine()->getManager();

        $my_open_codes = $em->getRepository('AppBundle:Code')->findBy(array('closed'=>0,'registrar'=>$current_app_user),array('createdAt'=>'DESC'));
        $old_codes = $em->getRepository('AppBundle:Code')->findBy(array('closed'=>0),array('createdAt'=>'DESC'));

        $granted = false;
        foreach ($old_codes as $code){
            $granted = $granted || $this->isGranted('view',$code);
        }
        if (!$granted){
            return $this->createAccessDeniedException('Oups, les anciens codes ne peuvent pas être lu par '.$current_app_user->getBeneficiary()->getFirstName());
        }

        $logger = $this->get('logger');

        if (count($my_open_codes)){
            $logger->info('CODE : code_new make change screen',array('username'=>$current_app_user->getUsername()));
            if (count($old_codes) > 1){
                return $this->render('default/code/new.html.twig', array(
                    'display' =>  true,
                    'code' => $my_open_codes[0],
                    'old_codes' => $old_codes,
                ));
            }else{
                return $this->render('default/code/new.html.twig', array(
                    'display' =>  true,
                    'code' => $my_open_codes[0],
                    'old_codes' => $my_open_codes,
                ));
            }

        }

        //no code open for this user

        if ($request->get('smartphone') === null){ //first visit
            $logger->info('CODE : code_new create screen',array('username'=>$current_app_user->getUsername()));
            return $this->render('default/code/new.html.twig');
        }

        $display = ($request->get('smartphone') == '0');

        $value = rand(0,9999);//code aléatoire à 4 chiffres
        $code = new Code();
        $code->setValue($value);

        $code->setClosed(false);
        $code->setCreatedAt(new \DateTime('now'));
        $code->setRegistrar($current_app_user);

        $em->persist($code);
        $em->flush();

        $logger->info('CODE : code_new created',array('username'=>$current_app_user->getUsername()));

        $dispatcher = $this->get('event_dispatcher');
        $dispatcher->dispatch(CodeNewEvent::NAME, new CodeNewEvent($code, $display,$old_codes));

        if ($request->get('smartphone') == '1'){ //send by email
            $session->getFlashBag()->add('success','Consulte ton espace membre ou tes emails depuis ton smartphone pour ouvrir le coffre, changer le code et déposer les clefs');
            return $this->redirectToRoute('homepage');
        }else{
            return $this->render('default/code/new.html.twig', array(
                'display' =>  $display,
                'no_smartphone' => true,
                'code' => $code,
                'old_codes' => $old_codes,
            ));
        }

    }

    /**
     *
     * @Route("/close/{id}", name="code_close")
     * @Method({"GET"})
     * @Security("has_role('ROLE_USER')")
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
     * @Route("/close_all", name="code_change_done")
     * @Method("GET")
     */
    public function closeAllButMineAction(Request $request){
        $session = new Session();
        $securityContext = $this->container->get('security.authorization_checker');

        $em = $this->getDoctrine()->getManager();
        $logged_out = false;
        $previousToken = null;

        $logger = $this->get('logger');

        if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
            $logger->info('CODE : confirm code change (logged in)',array('username'=>$current_app_user->getUsername()));
        }else{
            $token = $request->get('token');
            $username = explode(',',$this->get('AppBundle\Helper\SwipeCard')->vigenereDecode($token))[0];
            $current_app_user = $em->getRepository('AppBundle:User')->findOneBy(array('username'=>$username));
            if ($current_app_user){
                $previousToken = $this->get("security.token_storage")->getToken();
                $logged_out = true;
                $token = new UsernamePasswordToken($current_app_user, null, "main", $current_app_user->getRoles());
                $this->get("security.token_storage")->setToken($token);
                $logger->info('CODE : confirm code change (logged out)',array('username'=>$current_app_user->getUsername()));
            }else{
                //mute
                return $this->redirectToRoute('homepage');
            }
        }

        $my_open_codes = $em->getRepository('AppBundle:Code')->findBy(array('closed'=>0,'registrar'=>$current_app_user),array('createdAt'=>'DESC'));
        $myLastCode = $my_open_codes[0];
        $codes = $em->getRepository('AppBundle:Code')->findBy(array('closed'=>0),array('createdAt'=>'DESC'));
        foreach ($codes as $code){
            if ($myLastCode->getCreatedAt()>$code->getCreatedAt()){ // only older than mine
                if ($code->getRegistrar() != $current_app_user){ // not mine
                    if ($securityContext->isGranted(CodeVoter::VIEW, $code)) { //only the ones I can see
                        $code->setClosed(true);
                        $em->persist($code);
                    }
                }
            }
        }
        $em->flush();

        if ($logged_out){
            $this->get("security.token_storage")->setToken($previousToken);
        }

        $session->getFlashBag()->add('success', 'Bien enregistré, merci !');

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
