<?php

namespace AppBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class ShiftAlertsEvent extends Event
{
    const NAME = 'shift.alert';

    private $alerts;
    private $date;
    private $email_template;
    private $mattermost_hook_url;

    public function __construct(array $alerts, \DateTime $date, $email_template = null, $mattermost_hook_url = null)
    {
        $this->alerts = $alerts;
        $this->date = $date;
        $this->email_template = $email_template;
        $this->mattermost_hook_url = $mattermost_hook_url;
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
     * @return string|null
     */
    public function getEmailTemplate()
    {
        return $this->email_template;
    }

    /**
     * @return string|null
     */
    public function getMattermostHookUrl()
    {
        return $this->mattermost_hook_url;
    }
}
