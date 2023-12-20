<?php
// src/App/Security/TaskVoter.php
namespace App\Security;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TaskVoter extends Voter
{
    const VIEW = 'view';
    const CREATE = 'create';
    const EDIT = 'edit';
    const DELETE = 'delete';

    private $decisionManager;

    public function __construct(AccessDecisionManagerInterface $decisionManager)
    {
        $this->decisionManager = $decisionManager;
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::CREATE, self::EDIT,self::VIEW,self::DELETE))) {
            return false;
        }

        // only vote on Task objects inside this voter
        if (!$subject instanceof Task) {
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
        $task = $subject;

        // you know $subject is a Post object, thanks to supports
        switch ($attribute) {
            case self::VIEW:
                return true;
            case self::CREATE:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canAdd($task, $user);
            case self::EDIT:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canEdit($task, $user);
            case self::DELETE:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canDelete($task, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canAdd(Task $task, User $user)
    {
        // if they can edit, they can add
        if ($this->canEdit($task,$user)) {
            return true;
        }
        if ($user->getBeneficiary()->getCommissions()) {
            return true;
        }

        return false;
    }


    private function canEdit(Task $task, User $user)
    {
            if ($task->getOwners()->contains($user->getBeneficiary())){
                return true;
            }
            foreach ($task->getCommissions() as $commission ){
                if ($commission->getBeneficiaries()->contains($user->getBeneficiary())){
                    return true;
                }
            }
        return false;
    }

    private function canDelete(Task $task, User $user)
    {
        if ($task->getOwners()->contains($user->getBeneficiary())){
            return true;
        }
        foreach ($task->getCommissions() as $commission ){
            if ($commission->getOwners()->contains($user->getBeneficiary())){
                return true;
            }
        }
        return false;
    }
}