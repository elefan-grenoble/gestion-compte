<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/account", name="account")
     */
    public function accountAction(Request $request){
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/find_me", name="find_me")
     */
    public function findUserAction(Request $request){
        return $this->render('default/find_me.html.twig');
    }
}
