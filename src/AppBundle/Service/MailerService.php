<?php

namespace AppBundle\Service;

class MailerService
{
    private $baseDomain;
    
    private $sendableEmails;

    public function __construct(
        string $baseDomain,
        array $sendableEmails
    ) {
        $this->baseDomain = $baseDomain;
        $this->sendableEmails = $sendableEmails;
    }

    /**
     * Check if the given email is a temporary one
     * @param string $email
     * @return bool
     */
    public function isTemporaryEmail(string $email) : bool
    {
        $pattern = $this->getTemporaryEmailPattern();
        preg_match_all($pattern, $email, $matches, PREG_SET_ORDER, 0);
        return count($matches) > 0;
    }

    public function getAllowedEmails() : array
    {
        return array_column($this->sendableEmails, 'address', 'from_name');
    }

    private function getTemporaryEmailPattern() : string
    {
        return '/(membres\\+[0-9]+@' . preg_quote($this->baseDomain) . ')/i';
    }
}