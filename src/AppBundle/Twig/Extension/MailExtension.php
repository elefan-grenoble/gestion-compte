<?php

namespace AppBundle\Twig\Extension;

use AppBundle\Service\MailerService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MailExtension extends AbstractExtension
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
            new TwigFilter('isTemporaryEmail', array($this, 'isTemporaryEmail')),
        );
    }

    public function isTemporaryEmail(string $email) : bool
    {
        return $this->mailerService->isTemporaryEmail($email);
    }
}