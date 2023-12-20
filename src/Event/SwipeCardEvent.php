<?php

namespace App\Event;

use App\Entity\SwipeCard;
use Symfony\Component\EventDispatcher\Event;

class SwipeCardEvent extends Event
{
    const SWIPE_CARD_SCANNED = 'swipe_card.scanned';

    private $swipeCard;
    /**
     * @var integer
     */
    private $beneficiaryCounter;

    public function __construct(SwipeCard $swipeCard = null, $beneficiaryCounter)
    {
        $this->swipeCard = $swipeCard;
        $this->beneficiaryCounter = $beneficiaryCounter;
    }

    /**
     * @return SwipeCard|null
     */
    public function getSwipeCard(): ?SwipeCard
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
