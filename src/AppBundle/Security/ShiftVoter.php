<?php
// src/AppBundle/Security/ShiftVoter.php
namespace AppBundle\Security;

use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ShiftVoter extends Voter
{
    const BOOK = 'book';
    const DISMISS = 'dismiss';
    private $decisionManager;

    public function __construct(AccessDecisionManagerInterface $decisionManager)
    {
        $this->decisionManager = $decisionManager;
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::BOOK,self::DISMISS))) {
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

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
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
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canBook($shift, $user);
            case self::DISMISS:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canDismiss($shift, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canBook(Shift $shift, User $user)
    {
        foreach ($user->getBeneficiaries() as $beneficiary){
            $bool = $user->canBook($beneficiary,$shift);
            if ($bool)
                return true;
        }
        return false;
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
        if ($user->getBeneficiaries()->contains($shift->getShifter())) {
            return true;
        }
        return false;
    }


}