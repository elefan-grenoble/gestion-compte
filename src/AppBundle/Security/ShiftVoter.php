<?php
// src/AppBundle/Security/ShiftVoter.php
namespace AppBundle\Security;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Service\ShiftService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ShiftVoter extends Voter
{
    const BOOK = 'book';
    const FREE = 'free';
    const REJECT = 'reject';
    const ACCEPT = 'accept';
    const LOCK = 'lock';
    const VALIDATE = 'validate';
    private $decisionManager;
    private $container;

    /**
     * @var ShiftService
     */
    private $shiftService;

    public function __construct(ContainerInterface $container, AccessDecisionManagerInterface $decisionManager)
    {
        $this->container = $container;
        $this->decisionManager = $decisionManager;
        $this->shiftService = $container->get("shift_service");
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::BOOK, self::REJECT, self::FREE, self::ACCEPT, self::LOCK, self::VALIDATE))) {
            return false;
        }

        // only vote on Task objects inside this voter
        if (!$subject instanceof Shift) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        // in most cases, the user must be logged in
        // exceptions for accept and reject: can be done without login
        if (!$user instanceof User) {
            if (!in_array($attribute, array(self::REJECT, self::ACCEPT)))
                return false;
            else
                $user = null;
        }

        // ROLE_SUPER_ADMIN can do anything! The power!
        if ($this->decisionManager->decide($token, array('ROLE_SUPER_ADMIN'))) {
            return true;
        }

        // you know $subject is a Task object, thanks to supports
        /** @var Task $task */
        $shift = $subject;

        // you know $subject is a Post object, thanks to supports
        switch ($attribute) {
            case self::BOOK:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN','ROLE_SHIFT_MANAGER'))) {
                    return true;
                }
                return $this->shiftService->isShiftBookable($shift, $user->getBeneficiary());
            case self::FREE:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN','ROLE_SHIFT_MANAGER'))) {
                    return true;
                }
                return $this->isShifter($shift, $user);
            case self::LOCK:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN','ROLE_SHIFT_MANAGER'))) {
                    return true;
                }
                return false;
            case self::REJECT:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN','ROLE_SHIFT_MANAGER'))) {
                    return true;
                }
                return $this->canReject($shift, $user);
            case self::ACCEPT:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN','ROLE_SHIFT_MANAGER'))) {
                    return true;
                }
                return $this->canAccept($shift, $user);
            case self::VALIDATE:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN','ROLE_SHIFT_MANAGER'))) {
                    return true;
                }
                return false;
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function isShifter(Shift $shift, User $user = null)
    {
        if ($user instanceof User) {
            return $user->getBeneficiary() === $shift->getShifter();
        }
        return false;
    }

    /**
     * Accept / Reject reserved shifts
     * We check if the user corresponds to the shift's last shifter
     */
    private function canReject(Shift $shift, User $user = null)
    {
        // user is logged in: we don't check the token
        if ($user instanceof User) {
            return $user->getBeneficiary() === $shift->getLastShifter();
        }
        // the user is not logged in: we check the token
        $token = $this->container->get('request_stack')->getCurrentRequest()->get('token');
        if ($shift->getId() && $shift->getLastShifter() && $token) {
            return $token == $shift->getTmpToken($shift->getLastShifter()->getId());
        }
        return false;
    }

    private function canAccept(Shift $shift, User $user = null)
    {
        return $this->canReject($shift, $user);
    }
}
