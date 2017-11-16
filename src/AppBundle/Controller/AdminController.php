<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Registration;
use AppBundle\Entity\User;
use AppBundle\Form\BeneficiaryType;
use AppBundle\Form\UserType;
use OAuth2\OAuth2;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\Validator\Constraints\NotBlank;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("admin")
 */
class AdminController extends Controller
{
    /**
     * Admin panel
     *
     * @Route("/", name="admin")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function indexAction()
    {
        return $this->render('admin/index.html.twig');
    }

    /**
     * Registration list
     *
     * @Route("/registrations", name="admin_registrations")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function registrationsAction(Request $request)
    {
        if (!($page = $request->get('page')))
            $page = 1;
        $limit = 50;
        $max = $this->getDoctrine()->getManager()->createQueryBuilder()->from('AppBundle\Entity\Registration', 'u')
            ->select('count(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $nb_of_pages = intval($max/$limit);
        $nb_of_pages += (($max % $limit) > 0) ? 1 : 0;
        $registrations = $this->getDoctrine()->getManager()
            ->getRepository('AppBundle:Registration')
            ->findBy(array(),array('date' => 'DESC'),$limit,($page-1)*$limit);
        return $this->render('admin/registrations.html.twig',array('registrations'=>$registrations,'page'=>$page,'nb_of_pages'=>$nb_of_pages));
    }

    /**
     *
     *
     * @Route("/clients", name="admin_clients")
     * @Method("GET")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function clientsAction()
    {
        $clients = $this->getDoctrine()->getManager()->getRepository('AppBundle:Client')->findAll();
        return $this->render('admin/clients.html.twig',array('clients'=>$clients));
    }

    /**
     * Add new Client //todo put this auto in service création
     *
     * @Route("/client_new", name="admin_client_new")
     * @Method({"GET","POST"})
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function newClientAction(Request $request){

        $form = $this->createFormBuilder()
            ->add('url', TextType::class, array('label' => 'url','attr' => array(
                'placeholder' => 'http://www.example.com',
            )))
            ->add('grant_types', ChoiceType::class,array('choices'  => array(
                OAuth2::GRANT_TYPE_AUTH_CODE => OAuth2::GRANT_TYPE_AUTH_CODE,
                OAuth2::GRANT_TYPE_IMPLICIT => OAuth2::GRANT_TYPE_IMPLICIT,
                OAuth2::GRANT_TYPE_USER_CREDENTIALS => OAuth2::GRANT_TYPE_USER_CREDENTIALS,
                OAuth2::GRANT_TYPE_CLIENT_CREDENTIALS => OAuth2::GRANT_TYPE_CLIENT_CREDENTIALS,
                OAuth2::GRANT_TYPE_REFRESH_TOKEN => OAuth2::GRANT_TYPE_REFRESH_TOKEN,
                OAuth2::GRANT_TYPE_EXTENSIONS => OAuth2::GRANT_TYPE_EXTENSIONS),'multiple'=>true))
            ->add('add', SubmitType::class, array('label' => 'Ajouter'))
            ->getForm();

        if ($form->handleRequest($request)->isValid()) {

            $url = $form->get('url')->getData();

            $clientManager = $this->container->get('fos_oauth_server.client_manager.default');
            $client = $clientManager->createClient();
            $client->setRedirectUris(array($url));
            $client->setAllowedGrantTypes($form->get('grant_types')->getData());
            $clientManager->updateClient($client);

            return $this->redirect($this->generateUrl('fos_oauth_server_authorize', array(
                'client_id' => $client->getPublicId(),
                'redirect_uri' => $url,
                'response_type' => 'code'
            )));
        }
        return $this->render('admin/client_new.html.twig', array(
            'form' => $form->createView()
        ));

    }
}
