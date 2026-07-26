<?php

namespace App\Event;

use App\Entity\SwipeCard;

class SwipeCardEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const SWIPE_CARD_SCANNED = 'swipe_card.scanned';

    private $swipeCard;

    /**
     * @var int
     */
    private $beneficiaryCounter;

    public function __construct(?SwipeCard $swipeCard = null, $beneficiaryCounter)
    {
        $this->swipeCard = $swipeCard;
        $this->beneficiaryCounter = $beneficiaryCounter;
    }

    public function getSwipeCard(): ?SwipeCard
    {
        return $this->swipeCard;
    }

    /**
     * @return int
     */
    public function getCounter()
    {
        return $this->beneficiaryCounter;
    }
}
