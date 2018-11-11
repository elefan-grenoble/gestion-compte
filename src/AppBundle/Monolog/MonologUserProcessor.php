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
        /** @var User $user */
        $user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;

        if (is_object($user)) {
            $text = $user->getId();
            $beneficiary = $user->getBeneficiary();
            if ($beneficiary) {
                $text .= ' (' . $beneficiary->getDisplayName() . ')';
            }
            $record['extra']['user'] = $text;
        } else if ($user) {
            $record['extra']['user'] = $user;
        }

        return $record;
    }
}