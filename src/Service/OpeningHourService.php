<?php

namespace App\Service;

use App\Entity\OpeningHour;
use App\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ClosingException;

class OpeningHourService
{
    /** How far ahead (in days) to look for the next opening, e.g. over a long closing period. */
    private const LOOK_AHEAD_DAYS = 60;

    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Whether the opening hours say that the shop is open (closing exceptions included).
     * It does not tell whether anyone is actually there: see getStatus() for that.
     */
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

    /**
     * Whether the opening hours say that the shop is closed. See isOpen().
     */
    public function isClosed(?\DateTime $date = null)
    {
        return !$this->isOpen($date);
    }

    /**
     * Actual state of the shop, with the next time it changes, e.g. to display
     * "Open → closes at 4pm" or "Closed → opens tomorrow at 9am".
     *
     * The shop is open when the opening hours say so AND someone is on a shift
     * (a shift with nobody on it does not count). Shifts outside the opening hours
     * do not open the shop. The next opening and closing follow the same rule.
     *
     * - open:    whether the shop is open
     * - next:    when it closes (if open) or reopens (if closed); null if it does not reopen soon
     * - nextDay: 'today', 'tomorrow' or 'later' (relative to $date); null when there is no next change
     * - closesEarly: when open, whether it closes before the end of the opening hours
     *                because nobody is on the following shift
     *
     * @return array{open: bool, next: ?\DateTime, nextDay: ?string, closesEarly: bool}
     */
    public function getStatus(?\DateTime $date = null): array
    {
        $date = $date ? clone $date : new \DateTime('now');

        $open = false;
        $next = null;
        $closesEarly = false;
        $periods = $this->getOpenPeriods($date);
        if ($periods) {
            // the first period ends after $date: either it contains it (open until its end) or it starts later
            [$start, $end, $openingHoursEnd] = $periods[0];
            $open = $start <= $date;
            $next = $open ? $end : $start;
            $closesEarly = $open && $end < $openingHoursEnd;
        }

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

        return ['open' => $open, 'next' => $next, 'nextDay' => $nextDay, 'closesEarly' => $closesEarly];
    }

    /**
     * Periods when the shop is open from $from on (opening hours AND staffed shifts),
     * sorted, not overlapping, and not ended before $from. Each one is [start, end,
     * end of the period of the opening hours it belongs to].
     *
     * @return array<int, array{0: \DateTime, 1: \DateTime, 2: \DateTime}>
     */
    private function getOpenPeriods(\DateTime $from): array
    {
        $until = (clone $from)->modify('+' . self::LOOK_AHEAD_DAYS . ' day');

        $openingHours = $this->getOpeningHoursPeriods($from, $until);
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
     * Periods of the opening hours (enabled kinds, closed days and days with a
     * closing exception left out), sorted and not overlapping.
     *
     * @return array<int, array{0: \DateTime, 1: \DateTime}>
     */
    private function getOpeningHoursPeriods(\DateTime $from, \DateTime $until): array
    {
        $slots = $this->getSlotsByDayOfWeek();
        if (!$slots) {
            return [];
        }

        $closedDays = [];
        foreach ($this->em->getRepository(ClosingException::class)->findFuturesOrOngoing($from) as $closingException) {
            $closedDays[$closingException->getDate()->format('Y-m-d')] = true;
        }

        $periods = [];
        for ($offset = 0; $offset <= self::LOOK_AHEAD_DAYS; ++$offset) {
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

    private function atTime(\DateTime $day, string $time): \DateTime
    {
        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $time));

        return (clone $day)->setTime($hours, $minutes, $seconds);
    }
}
