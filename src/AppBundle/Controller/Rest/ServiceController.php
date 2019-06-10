<?php

namespace AppBundle\Controller\Rest;

use AppBundle\Entity\User;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Security("has_role('ROLE_USER')")
 */
class ServiceController extends AbstractFOSRestController
{
    /**
     * @Rest\Get("/services")
     */
    public function getServicesAction()
    {
        $em = $this->getDoctrine()->getManager();
        $services = $em->getRepository('AppBundle:Service')->findBy(array('public'=>1));
        return $services;
    }

}
