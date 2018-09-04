<?php

namespace AppBundle\Controller;

use OAuth2\OAuth2;
use Ornicar\GravatarBundle\GravatarApi;
use Ornicar\GravatarBundle\Templating\Helper\GravatarHelper;
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
     * @Route("/oauth/user", name="api_user")
     * @Method({"GET"})
     * @Security("has_role('ROLE_OAUTH_LOGIN')")
     */
    public function userAction()
    {
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        return new JsonResponse(array('user'=>array(
                'email' => $current_app_user->getEmail(),
                'username' => $current_app_user->getUserName(),
                'identifier' => $current_app_user->getId()
        )));
    }

    /**
     * @Route("/v4/user", name="api_gitlab_user")
     * @Method({"GET"})
     */
    public function gitlabUserAction()
    {
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }
        $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
        $gravatar_helper = new GravatarHelper(new GravatarApi());
        return new JsonResponse(array(
            'id' => $current_app_user->getMemberNumber(),
            'username' => $current_app_user->getUsername(),
            'email' => $current_app_user->getEmail(),
            'name' => $current_app_user->getFirstName().' '.$current_app_user->getlastname(),
            'state' => ($current_app_user->isEnabled()) ? "active" : "",
            'avatar_url' => $gravatar_helper->getUrl($current_app_user->getEmail()),
            'web_url' => "",
            "created_at" => "2012-05-23T08:00:58Z",
            "bio" => '',
            "location" => null,
            "skype" => "",
            "linkedin" => "",
            "twitter" => "",
            "website_url" => "",
            "organization" => "",
            "last_sign_in_at" => "2012-06-01T11:41:01Z",
            "confirmed_at" => "2012-05-23T09:05:22Z",
            "theme_id" => 1,
            "last_activity_on" => "2012-05-23",
            "color_scheme_id" => 2,
            "projects_limit" => 100,
            "current_sign_in_at" => "2012-06-02T06:36:55Z",
            "identities" => array(),
            "can_create_group" => true,
            "can_create_project" => true,
            "two_factor_enabled" => false,
            "external" => false
        ));
    }

}
