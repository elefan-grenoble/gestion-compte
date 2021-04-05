<?php
// src/App/Security/SwipeCardVoter.php
namespace App\Security;

use App\Entity\SwipeCard;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SwipeCardVoter extends Voter
{
    const PAIR = 'pair';
    const DISABLE = 'disable';
    const ENABLE = 'enable';
    const DELETE = 'delete';

    private $decisionManager;

    public function __construct(AccessDecisionManagerInterface $decisionManager)
    {
        $this->decisionManager = $decisionManager;
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::PAIR,self::DISABLE,self::ENABLE,self::DELETE,))) {
            return false;
        }

        // only vote on SwipeCard objects inside this voter
        if (!$subject instanceof SwipeCard) {
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

        // you know $subject is a Task object, thanks to supports
        /** @var SwipeCard $swipeCard */
        $swipeCard = $subject;

        $allowedRoles = ['ROLE_ADMIN', 'ROLE_USER_MANAGER'];

        // you know $subject is a Post object, thanks to supports
        switch ($attribute) {
            case self::DELETE:
                if ($this->decisionManager->decide($token, $allowedRoles)) {
                    return true;
                }
                return false;
            case self::DISABLE:
                if ($this->decisionManager->decide($token, $allowedRoles)) {
                    return true;
                }
                return $this->own($swipeCard, $user);
            case self::ENABLE:
                if ($this->decisionManager->decide($token, $allowedRoles)) {
                    return true;
                }
                return $this->own($swipeCard, $user);
            case self::PAIR:
                if ($this->decisionManager->decide($token, $allowedRoles)) {
                    return true;
                }
                return $this->canPair($swipeCard, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function own(SwipeCard $swipeCard, User $user)
    {
        if ($swipeCard->getBeneficiary()->getUser() === $user) {
            return true;
        }
        return false;
    }

    private function canPair(SwipeCard $swipeCard, User $user)
    {
        if ($user->getBeneficiary()->getSwipeCards()->count() === 0) {
            return true;
        }
        if ($user->getBeneficiary()->getEnabledSwipeCards()->count() === 0) {
            return true;
        }
        return false;
    }

}