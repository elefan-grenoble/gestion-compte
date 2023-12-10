<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Code;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Code controller.
 *
 * @Route("code")
 */
class CodeController extends Controller
{
    public function homepageDashboardAction()
    {
        $em = $this->getDoctrine()->getManager();
        $codes = $em->getRepository('AppBundle:Code')->findActiveCodesToDisplay();

        return $this->render('default/code/home_dashboard.html.twig', array('codes' => $codes));
    }

    /**
     * Lists all codes.
     *
     * @Route("/", name="code_index", methods={"GET"})
     * @Security("has_role('ROLE_USER')")
     */
    public function listAction(Request $request)
    {
        $session = new Session();
        $em = $this->getDoctrine()->getManager();

        $code_devices = $em->getRepository('AppBundle:CodeDevice')->findAll();
        $active_codes = $em->getRepository('AppBundle:Code')->findActiveCodesToDisplay();

        return $this->render('default/code/list.html.twig', array(
            'codes' => $active_codes,
            'code_devices' => $code_devices,
        ));
    }

}
