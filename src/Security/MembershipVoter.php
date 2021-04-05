<?php
// src/App/Security/UserVoter.php
namespace App\Security;

use App\Entity\Membership;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MembershipVoter extends Voter
{
    const ACCESS_TOOLS = 'access_tools';
    const CREATE = 'create';
    const VIEW = 'view';
    const EDIT = 'edit';
    const OPEN = 'open';
    const CLOSE = 'close';
    const FREEZE = 'freeze';
    const FREEZE_CHANGE = 'freeze_change';
    const ROLE_REMOVE = 'role_remove';
    const ROLE_ADD = 'role_add';
    const ANNOTATE = 'annotate';
    const BENEFICIARY_ADD = 'beneficiary_add';

    private $decisionManager;
    /**
     * @var RequestStack
     */
    private $requestStack;
    private $placeLocalIpAddress;

    public function __construct(AccessDecisionManagerInterface $decisionManager, RequestStack $requestStack, $placeLocalIpAddress)
    {
        $this->decisionManager = $decisionManager;
        $this->requestStack = $requestStack;
        $this->placeLocalIpAddress = $placeLocalIpAddress;
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(
                self::VIEW,
                self::EDIT,
                self::OPEN,
                self::CLOSE,
                self::ROLE_REMOVE,
                self::ROLE_ADD,
                self::FREEZE,
                self::FREEZE_CHANGE,
                self::CREATE,
                self::ANNOTATE,
                self::ACCESS_TOOLS,
                self::BENEFICIARY_ADD)
        )) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof Membership) {
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
        // on user ROLE_USER_MANAGER can do anything! The power!
        if ($this->decisionManager->decide($token, array('ROLE_USER_MANAGER'))) {
            return true;
        }

        // you know $subject is a Post object, thanks to supports
        switch ($attribute) {
            case self::ACCESS_TOOLS:
            case self::BENEFICIARY_ADD:
            case self::CREATE:
                return $this->isLocationOk();
            case self::VIEW:
            case self::ANNOTATE:
                return $this->canView($subject, $token);
            case self::FREEZE_CHANGE:
                if ($user->getBeneficiary() && $user->getBeneficiary()->getMembership() === $subject) {
                    return true;
                }
            case self::FREEZE:
            case self::OPEN:
            case self::CLOSE:
            case self::ROLE_ADD:
            case self::ROLE_REMOVE:
            case self::EDIT:
                return $this->canEdit($subject, $token);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(Membership $subject, TokenInterface $token)
    {
        // if they can edit, they can view
        if ($this->canEdit($subject, $token)) {
            return true;
        }

        if ($this->decisionManager->decide($token, ['ROLE_USER_VIEWER'])) {
            return true;
        }

        return false;
    }

    private function canEdit(Membership $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if ($user->getBeneficiary()->getMembership()->getId() === $subject->getId()) { //beneficiaries can edit there own membership
            return true;
        }

        if ($this->decisionManager->decide($token, ['ROLE_USER_MANAGER'])) {
            return true;
        }

        $session = $this->requestStack->getCurrentRequest()->getSession();
        $token = $this->requestStack->getCurrentRequest()->get('token');
        if ($token && $token == $subject->getTmpToken($session->get('token_key') . $user->getUsername())){
            return true;
        }

        return false;

    }

    private function isLocationOk()
    {
        $ip = $this->requestStack->getCurrentRequest()->getClientIp();
        $ips = $this->placeLocalIpAddress;
        $ips = explode(',', $ips);
        return (isset($ip) and in_array($ip, $ips));
    }
}