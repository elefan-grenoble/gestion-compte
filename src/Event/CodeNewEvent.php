<?php

namespace App\Event;

use App\Entity\Code;

class CodeNewEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const NAME = 'code.new';

    private $code;
    private $old_codes;

    public function __construct(Code $code, $old_codes)
    {
        $this->code = $code;
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
}
