<?php

namespace App\Service;

use App\Entity\OpeningHour;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ClosingException;

class OpeningHourService
{
    /** How far ahead (in days) to look for the next opening, e.g. over a long closing period. */
    private const NEXT_OPENING_HORIZON_DAYS = 60;

    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function isOpen(?\DateTime $date = null)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }

        // filter on day
        $openingHoursEnabledDay = $this->em->getRepository(OpeningHour::class)->findByDay($date, null, true, true);

        // filter on time
        if (count($openingHoursEnabledDay) > 0) {
            $openingHoursEnabledDayTime = array_filter($openingHoursEnabledDay, function ($openingHour) use ($date) {
                $openingHourStart = $openingHour->getStart()->setDate($date->format('Y'), $date->format('m'), $date->format('d'));
                $openingHourEnd = $openingHour->getEnd()->setDate($date->format('Y'), $date->format('m'), $date->format('d'));

                return ($openingHourStart <= $date) && ($openingHourEnd >= $date);
            });

            // final check on closing exceptions
            if (count($openingHoursEnabledDayTime) > 0) {
                $closingExceptions = $this->em->getRepository(ClosingException::class)->findOngoing($date);
                if (!$closingExceptions) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isClosed(?\DateTime $date = null)
    {
        return !$this->isOpen($date);
    }

    /**
     * State of the shop with the next time it changes, e.g. to display
     * "Open (closes at 6pm)" or "Closed (opens tomorrow at 9am)".
     *
     * - open:    whether the shop is open
     * - next:    when it closes (if open) or reopens (if closed); null if it does not reopen soon
     * - nextDay: 'today', 'tomorrow' or 'later' (relative to $date); null when there is no next change
     *
     * @return array{open: bool, next: ?\DateTime, nextDay: ?string}
     */
    public function getStatus(?\DateTime $date = null): array
    {
        $date = $date ? clone $date : new \DateTime('now');

        $open = $this->isOpen($date);
        $next = $open ? $this->getNextClosing($date) : $this->getNextOpening($date);

        $nextDay = null;
        if ($next) {
            if ($next->format('Y-m-d') === $date->format('Y-m-d')) {
                $nextDay = 'today';
            } elseif ($next->format('Y-m-d') === (clone $date)->modify('+1 day')->format('Y-m-d')) {
                $nextDay = 'tomorrow';
            } else {
                $nextDay = 'later';
            }
        }

        return ['open' => $open, 'next' => $next, 'nextDay' => $nextDay];
    }

    /**
     * When the ongoing opening ends: the end of the slot containing $date, extended
     * by the slots that follow without a break. Null if the shop is not open.
     */
    public function getNextClosing(?\DateTime $date = null): ?\DateTime
    {
        $date = $date ? clone $date : new \DateTime('now');

        if ($this->em->getRepository(ClosingException::class)->findOngoing($date)) {
            return null;
        }

        $slots = $this->getSlotsByDayOfWeek();
        $closing = null;
        foreach ($slots[(int) $date->format('N') - 1] ?? [] as [$start, $end]) {
            $slotStart = $this->atTime($date, $start);
            $slotEnd = $this->atTime($date, $end);

            if (null === $closing) {
                if ($slotStart <= $date && $date <= $slotEnd) {
                    $closing = $slotEnd;
                }
            } elseif ($slotStart <= $closing && $slotEnd > $closing) {
                $closing = $slotEnd;
            }
        }

        return $closing;
    }

    /**
     * When the shop opens next, strictly after $date. Days with a closing exception
     * are skipped. Null if it does not open within the next NEXT_OPENING_HORIZON_DAYS days.
     */
    public function getNextOpening(?\DateTime $date = null): ?\DateTime
    {
        $date = $date ? clone $date : new \DateTime('now');

        $slots = $this->getSlotsByDayOfWeek();
        if (!$slots) {
            return null;
        }

        $closedDays = [];
        foreach ($this->em->getRepository(ClosingException::class)->findFuturesOrOngoing($date) as $closingException) {
            $closedDays[$closingException->getDate()->format('Y-m-d')] = true;
        }

        for ($offset = 0; $offset <= self::NEXT_OPENING_HORIZON_DAYS; ++$offset) {
            $day = (clone $date)->modify('+' . $offset . ' day');
            if (isset($closedDays[$day->format('Y-m-d')])) {
                continue;
            }

            foreach ($slots[(int) $day->format('N') - 1] ?? [] as [$start]) {
                $opening = $this->atTime($day, $start);
                if ($opening > $date) {
                    return $opening;
                }
            }
        }

        return null;
    }

    /**
     * Opening slots (of the enabled kinds, closed days excluded) as [start, end]
     * times of day ('H:i:s'), by day of week (0 = Monday), sorted by start.
     */
    private function getSlotsByDayOfWeek(): array
    {
        $slots = [];
        foreach ($this->em->getRepository(OpeningHour::class)->findOngoing() as $openingHour) {
            if ($openingHour->getClosed() || !$openingHour->getStart() || !$openingHour->getEnd()) {
                continue;
            }
            $slots[(int) $openingHour->getDayOfWeek()][] = [
                $openingHour->getStart()->format('H:i:s'),
                $openingHour->getEnd()->format('H:i:s'),
            ];
        }
        foreach ($slots as &$daySlots) {
            sort($daySlots);
        }

        return $slots;
    }

    private function atTime(\DateTime $day, string $time): \DateTime
    {
        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $time));

        return (clone $day)->setTime($hours, $minutes, $seconds);
    }
}
