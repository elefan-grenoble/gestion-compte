<?php
/**
 * Created by PhpStorm.
 * User: gjanssens
 * Date: 27/09/17
 * Time: 18:13
 */
namespace AppBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use AppBundle\Entity\User;

class ChangeUserPasswordEvent extends Event {
    protected $user;
    protected $password;

    public function __construct(User $user, $password){
        $this->user = $user;
        $this->password = $password;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getPassword()
    {
        return $this->password;
    }
}