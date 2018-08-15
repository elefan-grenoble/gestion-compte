<?php
// src/AppBundle/Security/CodeVoter.php
namespace AppBundle\Security;

use AppBundle\Entity\Code;
use AppBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CodeVoter extends Voter
{
    const VIEW = 'view';
    const CREATE = 'create';
    const EDIT = 'edit';
    const DELETE = 'delete';

    private $decisionManager;
    private $container;

    public function __construct(ContainerInterface $container,AccessDecisionManagerInterface $decisionManager)
    {
        $this->container = $container;
        $this->decisionManager = $decisionManager;
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::CREATE, self::EDIT,self::VIEW,self::DELETE))) {
            return false;
        }

        // only vote on Code objects inside this voter
        if (!$subject instanceof Code) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
        }

        // ROLE_SUPER_ADMIN can do anything! The power!
        if ($this->decisionManager->decide($token, array('ROLE_SUPER_ADMIN'))) {
            return true;
        }

        // you know $subject is a Code object, thanks to supports
        /** @var Code $code */
        $code = $subject;

        // you know $subject is a Post object, thanks to supports
        switch ($attribute) {
            case self::VIEW:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canView($code, $user);
            case self::CREATE:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canAdd($code, $user);
            case self::EDIT:
            case self::DELETE:
                if ($this->decisionManager->decide($token, array('ROLE_SUPER_ADMIN'))) {
                    return true;
                }
                return $this->canDelete($code, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canAdd(Code $code, User $user)
    {
        if ($user->getCommissions()){ // si l'utilisateur fait parti d'une comm
            //return true;
        }
        $shifts = $user->getShiftsOfCycle(0);
        $y = new \DateTime('Yesterday');
        $y->setTime(23,59,59);
        $n = new \DateTime();
        foreach ($shifts as $shift){
            if (($shift->getStart() < $n) && $shift->getStart() > $y){ // si l'utilisateur à un créneau aujourd'hui qu'il a commencé
                return true;
            }
        }
        if ($this->isLocationOk()){ // si l'utilisateur est physiquement à l'épicerie
            return true;
        }

        return false;
    }


    private function canView(Code $code, User $user)
    {
        $shifts = $user->getShiftsOfCycle(0);
        $y = new \DateTime('Yesterday');
        $y->setTime(23,59,59);
        $n = new \DateTime();
        $n->add(new \DateInterval("P15M")); //TODO put in conf
        foreach ($shifts as $shift){
            if (($shift->getStart() < $n) && $shift->getStart() > $y && ($shift->getEnd() > $n)){ // si l'utilisateur à un créneau aujourd'hui qu'il a commencé et qu'il n'est pas fini
                return true;
            }
        }

        if ($code->getRegistrar() === $user){
            return true;
        }

        return false;
    }

    private function canDelete(Code $code, User $user)
    {
        return false;
    }

    //\AppBundle\Security\UserVoter::isLocationOk DUPLICATED
    private function isLocationOk(){
        $ip = $this->container->get('request_stack')->getCurrentRequest()->getClientIp();
        return (isset($ip) and in_array($ip,array('127.0.0.1','78.209.62.101','193.33.56.47'))); //todo put this in conf
    }
}