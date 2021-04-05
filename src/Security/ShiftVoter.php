<?php
// src/App/Security/ShiftVoter.php
namespace App\Security;

use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\User;
use App\Service\ShiftService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ShiftVoter extends Voter
{
    const BOOK = 'book';
    const FREE = 'free';
    const DISMISS = 'dismiss';
    const REJECT = 'reject';
    const ACCEPT = 'accept';
    const LOCK = 'lock';
    private $decisionManager;

    /**
     * @var ShiftService
     */
    private $shiftService;
    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(AccessDecisionManagerInterface $decisionManager, ShiftService $shiftService, RequestStack $requestStack)
    {
        $this->decisionManager = $decisionManager;
        $this->shiftService = $shiftService;
        $this->requestStack = $requestStack;
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::BOOK, self::DISMISS, self::REJECT, self::FREE, self::ACCEPT, self::LOCK))) {
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

        if (!$user instanceof User) {  // the user must be logged in; if not, deny access
            if (!in_array($attribute, array(self::REJECT, self::ACCEPT))) //accept and reject can be done without login
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
            case self::LOCK:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN','ROLE_SHIFT_MANAGER'))) {
                    return true;
                }
                return false;
            case self::DISMISS:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN','ROLE_SHIFT_MANAGER'))) {
                    return true;
                }
                return $this->canDismiss($shift, $user);
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
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canDismiss(Shift $shift, User $user)
    {
        if ($shift->getIsDismissed()) {
            return false;
        }
        if (!$shift->getShifter()) {
            return false;
        }
        if ($shift->getIsPast()) {
            return false;
        }
        if ($user->getBeneficiary() === $shift->getShifter()) {
            return true;
        }
        return false;
    }

    private function canReject(Shift $shift, User $user = null)
    {
        if ($user instanceof User) {  // the user is logged in
            return $user->getBeneficiary() === $shift->getLastShifter();
        } // the user is not logged in
        $token = $this->requestStack->getCurrentRequest()->get('token');
        if ($shift->getId()) {
            if ($shift->getLastShifter()) {
                if ($token == $shift->getTmpToken($shift->getLastShifter()->getId())) {
                    return true;
                }
            } else {
                return false;
            }
        }
        return false;
    }

    private function canAccept(Shift $shift, User $user = null)
    {
        return $this->canReject($shift, $user);
    }


}
