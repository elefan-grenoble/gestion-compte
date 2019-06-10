<?php

namespace AppBundle\Controller\Rest;

use AppBundle\Entity\User;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 */
class AuthController extends AbstractFOSRestController
{

    /**
     * @Rest\Get("/auth")
     */
    public function getAuthAction()
    {
        $session = new AuthSession();

        $user = $this->getUser();
        if ($user) {
            $session->user = $user;
        }

        $ip = $this->container->get('request_stack')->getCurrentRequest()->getClientIp();
        $session->ip = $ip;

        $session->trustedIp = true;

        return $session;
    }
}
