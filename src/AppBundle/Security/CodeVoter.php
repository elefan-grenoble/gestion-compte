<?php
// src/AppBundle/Security/CodeVoter.php
namespace AppBundle\Security;

use AppBundle\Entity\Code;
use AppBundle\Entity\User;
use AppBundle\Helper\PlaceIP;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CodeVoter extends Voter
{
    const VIEW = 'view';
    const GENERATE = 'generate';
    const EDIT = 'edit';
    const DELETE = 'delete';
    const DEACTIVATE = 'deactivate';
    const ACTIVATE = 'activate';

    private $decisionManager;
    private $container;

    public function __construct(ContainerInterface $container, AccessDecisionManagerInterface $decisionManager)
    {
        $this->container = $container;
        $this->decisionManager = $decisionManager;
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::GENERATE, self::EDIT, self::VIEW, self::DELETE, self::ACTIVATE, self::DEACTIVATE))) {
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
                if ($this->decisionManager->decide($token, array('ROLE_CODE_MANAGER'))) {
                    return true;
                }
                return $this->canView($code, $user);
            case self::GENERATE:
                if ($this->decisionManager->decide($token, array('ROLE_CODE_MANAGER'))) {
                    return true;
                }
            case self::ACTIVATE:
            case self::DEACTIVATE:
            case self::EDIT:
                if ($this->decisionManager->decide($token, array('ROLE_CODE_MANAGER'))) {
                    return true;
                }
            case self::DELETE:
                if ($this->decisionManager->decide($token, array('ROLE_SUPER_ADMIN'))) {
                    return true;
                }
        }

        throw new \LogicException('This code should not be reached!');
    }


    private function canView(Code $code, User $user)
    {
        if (!$code->getId())
            return false;

        if ($code->getRegistrar() === $user) { // my code
            return true;
        }

        if ($user->getBeneficiary()) {
            if ($this->container->get("shift_service")->isBeginner($user->getBeneficiary())) { // not for beginner
                return false;
            }

            $start_after = new \DateTime('Yesterday');
            $start_after->setTime(23, 59, 59);
            $end_after = new \DateTime();
            $end_after->sub(new \DateInterval("PT2H")); //time - 120min TODO put in conf
            $start_before = new \DateTime();
            $start_before->add(new \DateInterval("PT1H")); //time + 60min TODO put in conf

            return $this->container->get("shift_service")->isBeneficiaryHasShifts($user->getBeneficiary(),
                $start_after,
                $start_before,
                $end_after,
                true
            );
        }

        return false;
    }

    private function canDelete(Code $code, User $user)
    {
        return false;
    }
}
