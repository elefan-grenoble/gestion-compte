<?php
namespace AppBundle\EventListener;

use AppBundle\Event\ChangeUserPasswordEvent;

class ChangeUserEventListener
{
    public function changePassword(ChangeUserPasswordEvent $event)
    {
        //change password where needed
        $newPassword = $event->getPassword();
        return '';
    }
}