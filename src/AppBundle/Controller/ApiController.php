<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use http\Env\Response;
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

    protected function getUserIfActive()
    {
        $user = $this->getUser();
        $beneficiary = $user->getBeneficiary();
        $withDrawn = false;
        if ($beneficiary) {
            $withDrawn = $beneficiary->getMembership()->isWithdrawn();
        }

        if ($withDrawn || !$user->isEnabled()){ // user inactif
            return array('user'=>false,'message'=>'User not found');
        }else{
            return array('user'=>$user);
        }
    }

    /**
     * @Route("/swipe/in", name="api_swipe_in")
     * @Method({"POST"})
     * @Security("has_role('ROLE_OAUTH_LOGIN')")
     */
    public function swipeInAction()
    {
        return new JsonResponse(array(
            'success' => true
        ));
    }

    /**
     * @Route("/oauth/user", name="api_user")
     * @Method({"GET"})
     * @Security("has_role('ROLE_OAUTH_LOGIN')")
     */
    public function userAction()
    {
        if ($this->get('security.authorization_checker')->isGranted('ROLE_PREVIOUS_ADMIN')) { //DO NOT ALLOW OAUTH ON LOGIN AS
            throw $this->createAccessDeniedException();
        }
        $response = $this->getUserIfActive();
        if (!$response['user']){
            return new JsonResponse($response);
        }
        return new JsonResponse(array('user'=>array(
                'email' => $response['user']->getEmail(),
                'username' => $response['user']->getUserName(),
        )));
    }

    /**
     * @Route("/oauth/nextcloud_user", name="api_nextcloud_user")
     * @Method({"GET"})
     * @Security("has_role('ROLE_OAUTH_LOGIN')")
     */
    public function nextcloudUserAction()
    {
        if ($this->get('security.authorization_checker')->isGranted('ROLE_PREVIOUS_ADMIN')) { //DO NOT ALLOW OAUTH ON LOGIN AS
            throw $this->createAccessDeniedException();
        }
        $response = $this->getUserIfActive();
        if (!$response['user']){
            return new JsonResponse($response);
        }
        return new JsonResponse(array(
            'email' => $response['user']->getEmail(),
            'displayName' => $response['user']->getFirstName() . ' ' . $response['user']->getLastName(),
            'identifier' => $response['user']->getUserName()
        ));
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
        if ($this->get('security.authorization_checker')->isGranted('ROLE_PREVIOUS_ADMIN')) { //DO NOT ALLOW OAUTH ON LOGIN AS
            throw $this->createAccessDeniedException();
        }
        $response = $this->getUserIfActive();
        if (!$response['user']){
            return new JsonResponse($response);
        }
        /** @var User $current_app_user */
        $current_app_user = $response['user'];
        $gravatar_helper = new GravatarHelper(new GravatarApi());
        return new JsonResponse(array(
            'id' => $current_app_user->getId(),
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
