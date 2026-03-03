<?php

namespace App\Service;

use App\Entity\Period;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PeriodService
{
    private $container;
    private $em;
    private $use_fly_and_fixed;
    private $fly_and_fixed_entity_flying;

    public function __construct(ContainerInterface $container, EntityManagerInterface $em)
    {
        $this->container = $container;
        $this->em = $em;
        $this->use_fly_and_fixed = $this->container->getParameter('use_fly_and_fixed');
        $this->fly_and_fixed_entity_flying = $this->container->getParameter('fly_and_fixed_entity_flying');
    }

    public function getDaysOfWeekArray()
    {
        return Period::DAYS_OF_WEEK;
    }

    public function getWeekCycleArray()
    {
        return Period::WEEK_CYCLE;
    }

    /**
     * Return true if at least one shifter (a.k.a. beneficiary) registered for
     * this period has a warning status, meaning with a withdrawn or frozen membership
     * of if the shifter is member of the flying team.
     *
     * useful only if the use_fly_and_fixed is activated
     *
     * @param String|null $weekCycle a string of the week to keep or null if no filter
     * @return bool
     */
    public function hasWarningStatus(Period $period, ?String $weekCycle=null): bool
    {
        if ($this->use_fly_and_fixed) {
            foreach ($period->getPositions() as $position) {
                if ($shifter = $position->getShifter()) {
                    $shifterIsFlying = ($this->fly_and_fixed_entity_flying == 'Beneficiary' and $shifter->isFlying()) or ($this->fly_and_fixed_entity_flying == 'Membership' and $shifter->getMembership()->isFlying());
                    if ((($weekCycle && $position->getWeekCycle()==$weekCycle) or !$weekCycle)
                        and ($shifterIsFlying
                        or $shifter->getMembership()->isFrozen()
                        or $shifter->getMembership()->isWithdrawn())) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
