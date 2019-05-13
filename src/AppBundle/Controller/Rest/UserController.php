<?php

namespace AppBundle\Controller\Rest;

use AppBundle\Entity\User;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Security("has_role('ROLE_ADMIN')")
 */
class UserController extends AbstractFOSRestController
{
    /**
     * @Rest\Get("/admin/users")Rl
     */
    public function getUsersAction()
    {
        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository(User::class)->findAll();

        return $users;
    }

    /**
     * @Rest\Get("/admin/users/{id}")
     */
    public function getUserAction(User $user)
    {
        return $user;
    }

    /**
     * @Rest\Get("/user")
     */
    public function getCurrentUserAction()
    {
        return $this->getUser();
    }
}
