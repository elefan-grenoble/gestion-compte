<?php

namespace AppBundle\Event;

use AppBundle\Entity\SwipeCard;
use Symfony\Component\EventDispatcher\Event;

class SwipeCardEvent extends Event
{
    const SWIPE_CARD_SCANNED = 'swipe_card.scanned';

    /**
     * @var SwipeCard
     */
    private $swipeCard;

    public function __construct(SwipeCard $swipeCard)
    {
        $this->swipeCard = $swipeCard;
    }

    /**
     * @return SwipeCard
     */
    public function getSwipeCard(): SwipeCard
    {
        return $this->swipeCard;
    }
}
