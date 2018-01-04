<?php

namespace AppBundle\Controller;

use OAuth2\OAuth2;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * User controller.
 *
 * @Route("api")
 */
class ApiController extends Controller
{

    /**
     * Admin panel
     *
     * @Route("/oauth/user", name="api_user")
     * @Method({"GET"})
     * @Security("has_role('ROLE_OAUTH_LOGIN')")
     */
    public function userAction()
    {
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        return new JsonResponse(array('user'=>array(
                'email' => $current_app_user->getEmail(),
                'username' => $current_app_user->getUserName()
        )));
    }

}
