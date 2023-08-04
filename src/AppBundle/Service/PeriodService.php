<?php

namespace AppBundle\Service;

use AppBundle\Entity\Period;

class PeriodService
{
    public function __construct()
    {
    }

    public function getDaysOfWeekArray()
    {
        return Period::DAYS_OF_WEEK;
    }

    public function getWeekCycleArray()
    {
        return Period::WEEK_CYCLE;
    }
}
