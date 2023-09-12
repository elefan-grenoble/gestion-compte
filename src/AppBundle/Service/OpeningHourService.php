<?php

namespace AppBundle\Service;

use DateTime;
use AppBundle\Entity\OpeningHour;
use Doctrine\ORM\EntityManagerInterface;

class OpeningHourService
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function isOpen(\DateTime $date = null)
    {
        if (!$date) {
            $date = new \DateTime('now');
        }

        // filter on day
        $openingHoursEnabledDay = $this->em->getRepository('AppBundle:OpeningHour')->findByDay($date, null, true, true);

        // filter on time
        if (count($openingHoursEnabledDay) > 0) {
            $openingHoursEnabledDayTime = array_filter($openingHoursEnabledDay, function($openingHour) use ($date) {
                $openingHourStart = $openingHour->getStart()->setDate($date->format('Y'), $date->format('m'), $date->format('d'));
                $openingHourEnd = $openingHour->getEnd()->setDate($date->format('Y'), $date->format('m'), $date->format('d'));
                return ($openingHourStart <= $date) && ($openingHourEnd >= $date);
            });

            // final check on closing exceptions
            if (count($openingHoursEnabledDayTime) > 0) {
                $closingExceptions = $this->em->getRepository('AppBundle:ClosingException')->findOngoing($date);
                if (!$closingExceptions) {
                    return True;
                }
            }
        }

        return False;
    }

    public function isClosed(\DateTime $date = null)
    {
        return !$this->isOpen($date);
    }
}
