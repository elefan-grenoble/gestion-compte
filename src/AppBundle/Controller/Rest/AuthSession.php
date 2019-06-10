<?php


namespace AppBundle\Controller\Rest;


use AppBundle\Entity\User;

class AuthSession
{
    /**
     * @var User
     */
    var $user;

    /**
     * @var string
     */
    var $ip;

    /**
     * @var boolean
     */
    var $trustedIp;

}