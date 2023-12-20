<?php

namespace App\Twig\Extension;

use App\Service\MailerService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

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
            new TwigFunction('isTemporaryEmail', array($this, 'isTemporaryEmail')),
        );
    }

    public function isTemporaryEmail(string $email) : bool
    {
        return $this->mailerService->isTemporaryEmail($email);
    }
}