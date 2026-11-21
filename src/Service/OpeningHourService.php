<?php

namespace App\Service;

use App\Entity\OpeningHour;
use App\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ClosingException;

class OpeningHourService
{
    /** How far ahead (in days) to look for the next opening, e.g. over a long closing period. */
    private const NEXT_OPENING_HORIZON_DAYS = 60;

    /**
     * Windows (in days from now) in which the next change of a status based on the shifts is
     * searched, narrowest first: most of the time the answer is in the first one, so the
     * shifts of the next 60 days are rarely loaded.
     */
    private const SHIFTS_LOOK_AHEAD_STEPS = [1, 3, 7, 30, self::NEXT_OPENING_HORIZON_DAYS];

    protected $em;

    /** @var bool whether the status also needs someone on a shift, see getStatus() */
    private $useShifts;

    public function __construct(EntityManagerInterface $em, bool $useShifts = false)
    {
        $this->em = $em;
        $this->useShifts = $useShifts;
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
     * "Open → closes at 6pm" or "Closed → opens tomorrow at 9am".
     *
     * By default only the opening hours (closing exceptions included) are used. When the
     * shifts are used too (OPENING_HOUR_USE_SHIFTS_IN_OPEN_CLOSED_HEADER), the shop is open when the
     * opening hours say so AND someone is registered on a shift, whether or not they swiped their
     * card (a shift with nobody on it does not count, nor does a shift outside the opening hours). The next opening and closing
     * follow the same rule.
     *
     * - open:        whether the shop is open
     * - next:        when it closes (if open) or reopens (if closed); null if it does not reopen soon
     * - nextDay:     'today', 'tomorrow', 'week' (within the next 7 days) or 'later' (relative to $date);
     *                null when there is no next change
     * - closesEarly: when open, whether it closes before the end of the opening hours because
     *                nobody is on the following shift (always false without the shifts)
     *
     * @return array{open: bool, next: ?\DateTime, nextDay: ?string, closesEarly: bool}
     */
    public function getStatus(?\DateTime $date = null): array
    {
        $date = $date ? clone $date : new \DateTime('now');

        if ($this->useShifts) {
            return $this->getStatusFromShifts($date);
        }

        // open and next come from the same data: there is a next closing only when the shop is open
        $closing = $this->getNextClosing($date);
        $open = null !== $closing;
        $next = $open ? $closing : $this->getNextOpening($date);

        return ['open' => $open, 'next' => $next, 'nextDay' => $this->getNextDay($date, $next), 'closesEarly' => false];
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

        $closedDays = $this->getClosedDays($date);

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
     * @return null|string 'today', 'tomorrow', 'week' (within the next 7 days) or 'later', relative to $date
     */
    private function getNextDay(\DateTime $date, ?\DateTime $next): ?string
    {
        if (!$next) {
            return null;
        }
        if ($next->format('Y-m-d') === $date->format('Y-m-d')) {
            return 'today';
        }
        if ($next->format('Y-m-d') === (clone $date)->modify('+1 day')->format('Y-m-d')) {
            return 'tomorrow';
        }
        if ($next->format('Y-m-d') < (clone $date)->modify('+7 day')->format('Y-m-d')) {
            return 'week';
        }

        return 'later';
    }

    /**
     * Status when the shifts count: see getStatus().
     *
     * The next change is searched in windows of increasing size (SHIFTS_LOOK_AHEAD_STEPS) to
     * keep the queries small, and the search stops at the first window with an open period.
     * That period is the right one whatever the window: the periods are sorted and the
     * window starts at $date, and a period never goes beyond the day it starts (nor do the
     * opening hours), so the window does not cut it.
     *
     * @return array{open: bool, next: ?\DateTime, nextDay: ?string, closesEarly: bool}
     */
    private function getStatusFromShifts(\DateTime $date): array
    {
        $open = false;
        $next = null;
        $closesEarly = false;

        $slots = $this->getSlotsByDayOfWeek();
        if ($slots) {
            $closedDays = $this->getClosedDays($date);
            foreach (self::SHIFTS_LOOK_AHEAD_STEPS as $days) {
                $periods = $this->getOpenPeriods($date, $days, $slots, $closedDays);
                if (!$periods) {
                    continue;
                }

                // the first period ends after $date: either it contains it (open until its end) or it starts later
                [$start, $end, $openingHoursEnd] = $periods[0];
                $open = $start <= $date;
                $next = $open ? $end : $start;
                $closesEarly = $open && $end < $openingHoursEnd;

                break;
            }
        }

        return ['open' => $open, 'next' => $next, 'nextDay' => $this->getNextDay($date, $next), 'closesEarly' => $closesEarly];
    }

    /**
     * Periods when the shop is open in the $days days from $from on (opening hours AND
     * shifts with someone on them), sorted, not overlapping, and not ended before $from.
     * Each one is [start, end, end of the period of the opening hours it belongs to].
     *
     * @param array<int, array<int, array{0: string, 1: string}>> $slots      see getSlotsByDayOfWeek()
     * @param array<string, bool>                                 $closedDays 'Y-m-d' => true
     *
     * @return array<int, array{0: \DateTime, 1: \DateTime, 2: \DateTime}>
     */
    private function getOpenPeriods(\DateTime $from, int $days, array $slots, array $closedDays): array
    {
        $until = (clone $from)->modify('+' . $days . ' day');

        $openingHours = $this->getOpeningHoursPeriods($from, $until, $days, $slots, $closedDays);
        if (!$openingHours) {
            return [];
        }

        $staffed = [];
        foreach ($this->em->getRepository(Shift::class)->findStaffedBetween($from, $until) as $shift) {
            $staffed[] = [$shift['start'], $shift['end']];
        }

        $periods = $this->intersect($openingHours, $this->merge($staffed));

        return array_values(array_filter($periods, function (array $period) use ($from) {
            return $period[1] >= $from;
        }));
    }

    /**
     * Periods of the opening hours in the $days days from $from on (closed days and days
     * with a closing exception left out), sorted and not overlapping.
     *
     * @param array<int, array<int, array{0: string, 1: string}>> $slots      see getSlotsByDayOfWeek()
     * @param array<string, bool>                                 $closedDays 'Y-m-d' => true
     *
     * @return array<int, array{0: \DateTime, 1: \DateTime}>
     */
    private function getOpeningHoursPeriods(\DateTime $from, \DateTime $until, int $days, array $slots, array $closedDays): array
    {
        $periods = [];
        for ($offset = 0; $offset <= $days; ++$offset) {
            $day = (clone $from)->modify('+' . $offset . ' day');
            if (isset($closedDays[$day->format('Y-m-d')])) {
                continue;
            }

            foreach ($slots[(int) $day->format('N') - 1] ?? [] as [$start, $end]) {
                $period = [$this->atTime($day, $start), $this->atTime($day, $end)];
                if ($period[1] >= $from && $period[0] <= $until) {
                    $periods[] = $period;
                }
            }
        }

        return $this->merge($periods);
    }

    /**
     * Days with a closing exception, from $from on.
     *
     * @return array<string, bool> 'Y-m-d' => true
     */
    private function getClosedDays(\DateTime $from): array
    {
        $closedDays = [];
        foreach ($this->em->getRepository(ClosingException::class)->findFuturesOrOngoing($from) as $closingException) {
            $closedDays[$closingException->getDate()->format('Y-m-d')] = true;
        }

        return $closedDays;
    }

    /**
     * Sorts the periods and joins those that overlap or touch.
     *
     * @param array<int, array{0: \DateTime, 1: \DateTime}> $periods
     *
     * @return array<int, array{0: \DateTime, 1: \DateTime}>
     */
    private function merge(array $periods): array
    {
        usort($periods, function (array $a, array $b) {
            return $a[0] <=> $b[0];
        });

        $merged = [];
        foreach ($periods as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start <= $merged[$last][1]) {
                if ($end > $merged[$last][1]) {
                    $merged[$last][1] = $end;
                }
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /**
     * Parts of the periods of $a that are also in a period of $b (both sorted and not
     * overlapping), as [start, end, end of the period of $a]. A part reduced to a
     * single instant is not a period.
     *
     * @param array<int, array{0: \DateTime, 1: \DateTime}> $a
     * @param array<int, array{0: \DateTime, 1: \DateTime}> $b
     *
     * @return array<int, array{0: \DateTime, 1: \DateTime, 2: \DateTime}>
     */
    private function intersect(array $a, array $b): array
    {
        $intersection = [];
        $i = 0;
        $j = 0;
        while ($i < count($a) && $j < count($b)) {
            $start = $a[$i][0] > $b[$j][0] ? $a[$i][0] : $b[$j][0];
            $end = $a[$i][1] < $b[$j][1] ? $a[$i][1] : $b[$j][1];
            if ($start < $end) {
                $intersection[] = [$start, $end, $a[$i][1]];
            }

            if ($a[$i][1] < $b[$j][1]) {
                ++$i;
            } else {
                ++$j;
            }
        }

        return $intersection;
    }

    /**
     * Opening slots (of the enabled kinds, closed days excluded) as [start, end]
     * times of day ('H:i:s'), by day of week (0 = Monday), sorted by start.
     *
     * @return array<int, array<int, array{0: string, 1: string}>>
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
