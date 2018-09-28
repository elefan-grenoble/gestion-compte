<?php

namespace AppBundle\Event;

use AppBundle\Entity\Code;
use Symfony\Component\EventDispatcher\Event;

class CodeNewEvent extends Event
{
    const NAME = 'code.new';

    private $code;
    private $display;
    private $old_codes;

    public function __construct(Code $code, $display, $old_codes)
    {
        $this->code = $code;
        $this->display = $display;
        $this->old_codes = $old_codes;
    }

    /**
     * @return Code
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return array
     */
    public function getOldCodes()
    {
        return $this->old_codes;
    }

    /**
     * @return boolean
     */
    public function getDisplay()
    {
        return $this->display;
    }

}
