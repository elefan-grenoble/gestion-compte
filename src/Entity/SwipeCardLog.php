<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SwipeCardLog
 *
 * @ORM\Table(name="swipe_card_log")
 * @ORM\Entity
 */
class SwipeCardLog
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     * @ORM\Column(name="counter", type="integer")
     */
    private $counter;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $date;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set date.
     *
     * @param \DateTime|null $date
     *
     * @return SwipeCardLog
     */
    public function setDate($date = null)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date.
     *
     * @return \DateTime|null
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set counter.
     *
     * @param integer $counter
     *
     * @return SwipeCardLog
     */
    public function setCounter($counter)
    {
        $this->counter = $counter;

        return $this;
    }

    /**
     * Get counter.
     *
     * @return integer $counter
     */
    public function getCounter()
    {
        return $this->counter;
    }
}
