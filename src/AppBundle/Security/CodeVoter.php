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
    const CLOSE = 'close';

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
        if (!in_array($attribute, array(self::CREATE, self::EDIT,self::VIEW,self::DELETE,self::CLOSE))) {
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
            case self::CLOSE:
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
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
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
        $now = new \DateTime('now');
        if ($this->canView($code, $user)){ //can add only if last code can be seen
            if ($code->getRegistrar() != $user || $code->getCreatedAt()->format('Y m d')!=($now->format('Y m d'))){ // on ne change pas son propre code
                if ($this->isLocationOk()){ // et si l'utilisateur est physiquement à l'épicerie
                    return true;
                }
            }
        }

        return false;
    }


    private function canView(Code $code, User $user)
    {
        if (!$code->getId())
            return false;

        if ($code->getRegistrar() === $user){ // my code
            return true;
        }

        if ($user->getBeneficiary()) {
            if ($this->container->get("shift_service")->isBeginner($user->getBeneficiary())) // not for beginner
                return false;
            $shifts = $user->getBeneficiary()->getMembership()->getShiftsOfCycle(0);
            $y = new \DateTime('Yesterday');
            $y->setTime(23,59,59);
            $some_time_ago = new \DateTime();
            $in_some_time = new \DateTime();
            $some_time_ago->sub(new \DateInterval("PT1H")); //time - 60min TODO put in conf
            $in_some_time->add(new \DateInterval("PT15M")); //time + 15min TODO put in conf
            foreach ($shifts as $shift){
                if (($shift->getStart() < $in_some_time) && $shift->getStart() > $y && ($shift->getEnd() > $some_time_ago)){ // si l'utilisateur à un créneau aujourd'hui qu'il a commencé et qu'il n'est pas fini
                    return true;
                }
            }
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
        $ips = $this->container->getParameter('place_local_ip_address');
        $ips = explode(',',$ips);
        return (isset($ip) and in_array($ip,$ips));
    }
}