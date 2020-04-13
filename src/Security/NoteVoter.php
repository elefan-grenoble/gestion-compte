<?php
// src/App/Security/NoteVoter.php
namespace App\Security;

use App\Entity\Note;
use App\Entity\User;
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
                return $this->canAdd($note, $token);
            case self::EDIT:
                return $this->canEdit($note, $token);
            case self::DELETE:
                return $this->canDelete($note, $token);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canAdd(Note $note, TokenInterface $token)
    {
        $user = $token->getUser();

        // if they can edit, they can add
        if ($this->canEdit($note, $token)) {
            return true;
        }
        // can add note on self
        if ($note->getSubject() === $user) {
            return true;
        }
        if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
            return true;
        }
        return false;
    }

    private function canEdit(Note $note, TokenInterface $token)
    {
        $user = $token->getUser();

        if ($note->getAuthor() === $user) {
            return true;
        }
        if (!$note->getSubject()) { //postit can be edited by anyone
            return true;
        }
        if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
            return true;
        }
        return false;
    }

    private function canDelete(Note $note, TokenInterface $token)
    {
        $user = $token->getUser();

        if ($note->getAuthor() === $user) {
            return true;
        }
        if ($note->getSubject() === $user) {
            return true;
        }
        if ($this->decisionManager->decide($token, ['ROLE_USER_MANAGER'])) {
            return true;
        }
        return false;
    }
}