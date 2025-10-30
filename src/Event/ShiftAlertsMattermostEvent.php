<?php

namespace App\Event;

use Symfony\Component\EventDispatcher\Event;

class ShiftAlertsMattermostEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    const NAME = 'shift.alerts.mattermost';

    private $alerts;
    private $date;
    private $template;
    private $mattermost_hook_url;

    public function __construct(array $alerts, \DateTime $date, $template = null, $mattermost_hook_url = null)
    {
        $this->alerts = $alerts;
        $this->date = $date;
        $this->template = $template;
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
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @return string|null
     */
    public function getMattermostHookUrl()
    {
        return $this->mattermost_hook_url;
    }
}
