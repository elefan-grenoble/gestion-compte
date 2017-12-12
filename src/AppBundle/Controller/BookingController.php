<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * User controller.
 *
 * @Route("booking")
 */
class BookingController extends Controller
{
    /**
     * @Route("/", name="booking")
     */
    public function indexAction(Request $request)
    {
        return $this->render('booking/index.html.twig', []);
    }
    
}
