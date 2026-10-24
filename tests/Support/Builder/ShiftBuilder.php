<?php

namespace App\Tests\Support\Builder;

use App\Entity\Beneficiary;
use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\User;

/**
 * Builds a free three-hour Shift starting tomorrow at 09:00, on a job of its
 * own unless one is given.
 *
 *     $shift = ShiftBuilder::aShift()->bookedBy($beneficiary)->build();
 *
 * Persisting the shift does not persist its job when the job is new: persist
 * $shift->getJob() too (the job cascades to its shifts, not the reverse).
 */
final class ShiftBuilder
{
    /** @var \DateTime */
    private $start;

    /** @var int */
    private $durationInMinutes = 180;

    /** @var null|Job */
    private $job;

    /** @var null|Beneficiary */
    private $shifter;

    /** @var null|User */
    private $booker;

    /** @var bool */
    private $carriedOut = false;

    /** @var bool */
    private $fixed = false;

    /** @var bool */
    private $locked = false;

    private function __construct()
    {
        $this->start = new \DateTime('tomorrow 09:00');
    }

    public static function aShift(): self
    {
        return new self();
    }

    public function startingAt(\DateTime $start): self
    {
        $this->start = clone $start;

        return $this;
    }

    public function lasting(int $minutes): self
    {
        $this->durationInMinutes = $minutes;

        return $this;
    }

    public function forJob(Job $job): self
    {
        $this->job = $job;

        return $this;
    }

    public function bookedBy(Beneficiary $shifter, ?User $booker = null): self
    {
        $this->shifter = $shifter;
        $this->booker = $booker ?? $shifter->getUser();

        return $this;
    }

    public function carriedOut(bool $carriedOut = true): self
    {
        $this->carriedOut = $carriedOut;

        return $this;
    }

    public function fixed(bool $fixed = true): self
    {
        $this->fixed = $fixed;

        return $this;
    }

    public function locked(bool $locked = true): self
    {
        $this->locked = $locked;

        return $this;
    }

    public function build(): Shift
    {
        $job = $this->job ?? self::newJob();

        $shift = new Shift();
        $shift->setStart(clone $this->start);
        $shift->setEnd((clone $this->start)->modify(sprintf('+%d minutes', $this->durationInMinutes)));
        $shift->setJob($job);
        $job->addShift($shift);
        $shift->setWasCarriedOut($this->carriedOut);
        $shift->setFixe($this->fixed);
        $shift->setLocked($this->locked);

        if (null !== $this->shifter) {
            $shift->setShifter($this->shifter);
            $shift->setBooker($this->booker);
            $shift->setBookedTime(new \DateTime());
            $this->shifter->addShift($shift);
        }

        return $shift;
    }

    private static function newJob(): Job
    {
        $job = new Job();
        $job->setName('test-job-' . UniqueSequence::next());
        $job->setColor('#cccccc');
        $job->setMinShifterAlert(2);
        $job->setEnabled(true);

        return $job;
    }
}
