<?php

namespace App\Event;

use Symfony\Component\EventDispatcher\Event;

class SwipeCardEvent extends Event
{
    const SWIPE_CARD_SCANNED = 'swipe_card.scanned';

    /**
     * @var integer
     */
    private $beneficiaryCounter;

    public function __construct($beneficiaryCounter)
    {
        $this->beneficiaryCounter = $beneficiaryCounter;
    }

    /**
     * @return integer
     */
    public function getCounter()
    {
        return $this->beneficiaryCounter;
    }
}
