<?php

namespace AppBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class SwipeCardEvent extends Event
{
    const SWIPE_CARD_SCANNED = 'swipe_card.scanned';

    /**
     * @var integer
     */
    private $beneficiaryCounter;

    public function __construct(SwipeCard $swipeCard, $beneficiaryCounter)
    {
        $this->swipeCard = $swipeCard;
        $this->beneficiaryCounter = $beneficiaryCounter;
    }

    /**
     * @return SwipeCard
     */
    public function getSwipeCard(): SwipeCard
    {
        return $this->swipeCard;
    }

    /**
     * @return integer
     */
    public function getCounter()
    {
        return $this->beneficiaryCounter;
    }
}
