<?php

namespace App\Controller;


use App\Entity\Code;
use App\Event\CodeNewEvent;
use App\Helper\SwipeCard;
use App\Security\CodeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * Code controller.
 *
 * @Route("codes")
 */
class CodeController extends Controller
{
    public function homepageDashboardAction(EntityManagerInterface $em)
    {
        $codes = $em->getRepository('App:Code')->findBy(array('closed' => 0), array('createdAt' => 'DESC'));
        if (!$codes) {
            $codes[] = new Code();
        }
        return $this->render('default/code/home_dashboard.html.twig',array('codes'=>$codes));
    }

    /**
     * Lists all codes.
     *
     * @Route("/", name="codes_list")
     * @Method("GET")
     * @Security("has_role('ROLE_USER')")
     */
    public function listAction(Request $request, LoggerInterface $logger, EntityManagerInterface $em)
    {
        $session = new Session();
        $logger->info('CODE : codes_list',array('username'=>$this->getUser()->getUsername()));

        if ($this->getUser()->hasRole('ROLE_ADMIN')){
            $codes = $em->getRepository('App:Code')->findBy(array(),array('createdAt'=>'DESC'),100);
            $old_codes =  null;
        }else{
            $codes = $em->getRepository('App:Code')->findBy(array('closed'=>0),array('createdAt'=>'DESC'),10);
            $old_codes = $em->getRepository('App:Code')->findBy(array('closed'=>1),array('createdAt'=>'DESC'),3);
        }

        if (!count($codes)){
            $session->getFlashBag()->add('warning', 'aucun code à lire');
            return $this->redirectToRoute('homepage');
        }

        $this->denyAccessUnlessGranted('view',$codes[0]);

        return $this->render('default/code/list.html.twig', array(
            'codes' => $codes,
            'old_codes' => $old_codes
        ));
    }

    /**
     * add new code.
     *
     * @Route("/new", name="code_edit")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function newAction(Request $request, EntityManagerInterface $em)
    {
        $session = new Session();

        $codeform = $this->createFormBuilder()
            ->setAction($this->generateUrl('code_edit'))
            ->setMethod('POST')
            ->add('code', TextType::class, array('label' => 'code', 'required' => true))
            ->add('close_old_codes', CheckboxType::class, array('label' => 'fermer les anciens codes ?', 'required' => false))
            ->getForm();

        $codeform->handleRequest($request);

        if ($codeform->isSubmitted() && $codeform->isValid()) {

            $value = $codeform->get('code')->getData();
            $code = new Code();
            $code->setValue($value);

            $code->setClosed(false);
            $code->setCreatedAt(new \DateTime('now'));
            $code->setRegistrar($this->getUser());

            $em->persist($code);

            if ($codeform->get('close_old_codes')->getData()){
                //close old codes
                $open_codes = $em->getRepository('App:Code')->findBy(array('closed' => 0));
                foreach ($open_codes as $open_code) {
                    $open_code->setClosed(true);
                    $em->persist($code);
                }
                //$session->getFlashBag()->add('success', 'Anciens codes fermés.');
            }

            $em->flush();

            $session->getFlashBag()->add('success', '🎉 Nouveau code enregistré.');

            return $this->redirectToRoute('codes_list');
        }

        return $this->render('default/code/new.html.twig', array(
            'form' => $codeform->createView()
        ));
    }
    /**
     * add new code.
     *
     * @Route("/generate", name="code_generate")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_USER')")
     */
    public function generateAction(Request $request, LoggerInterface $logger, EntityManagerInterface $em, EventDispatcherInterface $dispatcher)
    {
        $session = new Session();
        $current_app_user = $this->getUser();

        $my_open_codes = $em->getRepository('App:Code')->findBy(array('closed'=>0,'registrar'=>$current_app_user),array('createdAt'=>'DESC'));
        $old_codes = $em->getRepository('App:Code')->findBy(array('closed'=>0),array('createdAt'=>'DESC'));

        $granted = false;
        foreach ($old_codes as $code){
            $granted = $granted || $this->isGranted('view',$code);
        }
        if (!$granted){
            return $this->createAccessDeniedException('Oups, les anciens codes ne peuvent pas être lu par '.$current_app_user->getBeneficiary()->getFirstName());
        }

        if (count($my_open_codes)){
            $logger->info('CODE : code_new make change screen',array('username'=>$current_app_user->getUsername()));
            if (count($old_codes) > 1){
                return $this->render('default/code/generate.html.twig', array(
                    'display' =>  true,
                    'code' => $my_open_codes[0],
                    'old_codes' => $old_codes,
                ));
            }else{
                return $this->render('default/code/generate.html.twig', array(
                    'display' =>  true,
                    'code' => $my_open_codes[0],
                    'old_codes' => $my_open_codes,
                ));
            }

        }

        //no code open for this user

        if ($request->get('generate') === null){ //first visit
            $logger->info('CODE : code_new create screen',array('username'=>$current_app_user->getUsername()));
            return $this->render('default/code/generate.html.twig');
        }

        $value = rand(0,9999);//code aléatoire à 4 chiffres
        $code = new Code();
        $code->setValue($value);

        $code->setClosed(false);
        $code->setCreatedAt(new \DateTime('now'));
        $code->setRegistrar($current_app_user);

        $em->persist($code);
        $em->flush();

        $logger->info('CODE : code_new created',array('username'=>$this->getUser()->getUsername()));
        $dispatcher->dispatch(CodeNewEvent::NAME, new CodeNewEvent($code, $old_codes));

        $session->getFlashBag()->add('success','🎉 Bravo ! Note bien les deux codes ci-dessous ! <br>Tu peux aussi retrouver ces infos dans tes mails.');

        return $this->render('default/code/generate.html.twig', array(
            'generate' =>  true,
            'code' => $code,
            'old_codes' => $old_codes,
        ));

    }

    /**
     *
     * @Route("/toggle/{id}", name="code_toggle")
     * @Method({"GET"})
     * @Security("has_role('ROLE_USER')")
     */
    public function toggleAction(Request $request, Code $code, EntityManagerInterface $em)
    {
        $session = new Session();

        if ($code->getClosed())
            $this->denyAccessUnlessGranted('open',$code);
        else
            $this->denyAccessUnlessGranted('close',$code);

        $code->setClosed(!$code->getClosed());

        $em->persist($code);
        $em->flush();

        $session->getFlashBag()->add('success', 'Le code a bien été marqué '.(($code->getClosed())?'fermé':'ouvert').' !');

        return $this->redirectToRoute('codes_list');
    }

    /**
     * close all codes.
     *
     * @Route("/close_all", name="code_change_done")
     * @Method("GET")
     */
    public function closeAllButMineAction(Request $request, LoggerInterface $logger, EntityManagerInterface $em, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage, SwipeCard $swipeCard)
    {
        $session = new Session();
        $logged_out = false;
        $previousToken = null;

        if ($authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $current_app_user = $this->getUser();
            $logger->info('CODE : confirm code change (logged in)',array('username'=>$current_app_user->getUsername()));
        }else{
            $token = $request->get('token');
            $username = explode(',', $swipeCard->vigenereDecode($token))[0];
            $current_app_user = $em->getRepository('App:User')->findOneBy(array('username'=>$username));
            if ($current_app_user){
                $previousToken = $tokenStorage->getToken();
                $logged_out = true;
                $token = new UsernamePasswordToken($current_app_user, null, "main", $current_app_user->getRoles());
                $tokenStorage->setToken($token);
                $logger->info('CODE : confirm code change (logged out)',array('username'=>$current_app_user->getUsername()));
            }else{
                //mute
                return $this->redirectToRoute('homepage');
            }
        }

        $my_open_codes = $em->getRepository('App:Code')->findBy(array('closed'=>0,'registrar'=>$current_app_user),array('createdAt'=>'DESC'));
        $myLastCode = $my_open_codes[0];
        $codes = $em->getRepository('App:Code')->findBy(array('closed'=>0),array('createdAt'=>'DESC'));
        foreach ($codes as $code){
            if ($myLastCode->getCreatedAt()>$code->getCreatedAt()){ // only older than mine
                if ($code->getRegistrar() != $current_app_user){ // not mine
                    if ($authorizationChecker->isGranted(CodeVoter::VIEW, $code)) { //only the ones I can see
                        $code->setClosed(true);
                        $em->persist($code);
                    }
                }
            }
        }
        $em->flush();

        if ($logged_out){
            $tokenStorage->setToken($previousToken);
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
    public function removeAction(Request $request, Code $code, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('delete',$code);
        $session = new Session();
        $form = $this->getDeleteForm($code);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
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
