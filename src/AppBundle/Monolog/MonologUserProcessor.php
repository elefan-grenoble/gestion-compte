<?php

namespace AppBundle\Monolog;

use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class MonologUserProcessor
{
    private $tokenStorage;

    public function __construct(TokenStorage $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function processRecord(array $record)
    {
        /** @var User $current_user */
        $current_user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;

        if (is_object($current_user)) {
            $text = $current_user->getId();
            $beneficiary = $current_user->getBeneficiary();
            if ($beneficiary) {
                $text .= ' (' . $beneficiary->getDisplayNameWithMemberNumber() . ')';
            }
            $record['extra']['user'] = $text;
        } else if ($current_user) {
            $record['extra']['user'] = $current_user;
        }

        return $record;
    }
}
