<?php

namespace AppBundle\Twig\Extension;

use AppBundle\Service\MailerService;

class MailExtension extends \Twig_Extension
{
    /**
     * @var MailerService
     */
    private $mailerService;

    public function __construct(MailerService $mailerService)
    {
        $this->mailerService = $mailerService;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('isTemporaryEmail', array($this, 'isTemporaryEmail')),
        );
    }

    public function isTemporaryEmail(string $email) : bool
    {
        return $this->mailerService->isTemporaryEmail($email);
    }
}