<?php
// src/AppBundle/Security/NoteVoter.php
namespace AppBundle\Security;

use AppBundle\Entity\Note;
use AppBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class NoteVoter extends Voter
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
        if (!$subject instanceof Note) {
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

        // you know $subject is a Note object, thanks to supports
        /** @var Note $note */
        $note = $subject;

        switch ($attribute) {
            case self::VIEW:
                return true;
            case self::CREATE:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canAdd($note, $user);
            case self::EDIT:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canEdit($note, $user);
            case self::DELETE:
                if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
                    return true;
                }
                return $this->canDelete($note, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canAdd(Note $note, User $user)
    {
        // if they can edit, they can add
        if ($this->canEdit($note,$user)) {
            return true;
        }
        // can add note on self
        if ($note->getSubject() === $user){
            return true;
        }
        return false;
    }

    private function canEdit(Note $note, User $user)
    {
        if ($note->getAuthor() === $user){
            return true;
        }
        if (!$note->getSubject()) //postit can be edited by anyone
            return true;
        return false;
    }

    private function canDelete(Note $note, User $user)
    {
        if ($note->getAuthor() === $user){
            return true;
        }
        if ($note->getSubject() === $user){
            return true;
        }
        if ($user->getMainBeneficiary()->canEditUserData()){ //todo check also other Beneficiary ?
            return true;
        }
        return false;
    }
}