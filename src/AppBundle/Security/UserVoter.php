<?php
// src/AppBundle/Security/UserVoter.php
namespace AppBundle\Security;

use AppBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    const ACCESS_TOOLS = 'access_tools';
    const CREATE = 'create';
    const VIEW = 'view';
    const EDIT = 'edit';
    const ANNOTATE = 'annotate';

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
        if (!in_array($attribute, array(self::VIEW, self::EDIT, self::CREATE,self::ANNOTATE, self::ACCESS_TOOLS))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof User) {
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
        // on user ROLE_ADMIN can do anything! The power!
        if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
            return true;
        }

        // you know $subject is a Post object, thanks to supports
        switch ($attribute) {
            case self::ACCESS_TOOLS:
            case self::CREATE:
                return $this->isLocationOk();
            case self::VIEW:
            case self::ANNOTATE:
                return $this->canView($subject, $user);
            case self::EDIT:
                return $this->canEdit($subject, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(User $subject, User $user)
    {
        // if they can edit, they can view
        if ($this->canEdit($subject, $user)) {
            return true;
        }
        if ($user->getMainBeneficiary()->canViewUserData()){ //todo check also other Beneficiary ?
            return true;
        }
        return false;
    }

    private function canEdit(User $subject, User $user)
    {
        $session = new Session();

        $token = $this->container->get('request_stack')->getCurrentRequest()->get('token');

        if ($this->isLocationOk()){
            if ($user->getMainBeneficiary()->canEditUserData()){ //todo check also other Beneficiary ?
                return true;
            }
            if ($subject->getId()){
                if ($token == $subject->getTmpToken($session->get('token_key').$user->getUsername())){
                    return true;
                }
            }
            return false;
        }
        return false;

    }

    private function isLocationOk(){
        $ip = $this->container->get('request_stack')->getCurrentRequest()->getClientIp();
        return (isset($ip) and in_array($ip,array('127.0.0.1','78.209.62.101','193.33.56.47'))); //todo put this in conf
    }
}