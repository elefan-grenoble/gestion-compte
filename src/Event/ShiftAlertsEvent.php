<?php

namespace App\Event;

class ShiftAlertsEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'shift.alerts';

    private $alerts;
    private $date;
    private $template;
    private $recipients;

    public function __construct(array $alerts, \DateTime $date, $template = null, $recipients = null)
    {
        $this->alerts = $alerts;
        $this->date = $date;
        $this->template = $template;
        $this->recipients = $recipients;
    }

    /**
     * @return array
     */
    public function getAlerts()
    {
        return $this->alerts;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @return null|string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @return null|array
     */
    public function getRecipients()
    {
        return $this->recipients;
    }
}
