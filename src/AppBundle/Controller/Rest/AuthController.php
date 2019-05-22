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
        $user = $this->getUser();
        $response = [        ];
        if ($user) {
            $response['user'] = $this->json($user);
        }
        return $this->json($response);
    }
}
