<?php

namespace App\Service;

use DateTime;
use App\Entity\OpeningHour;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ClosingException;

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
        $openingHoursEnabledDay = $this->em->getRepository(OpeningHour::class)->findByDay($date, null, true, true);

        // filter on time
        if (count($openingHoursEnabledDay) > 0) {
            $openingHoursEnabledDayTime = array_filter($openingHoursEnabledDay, function($openingHour) use ($date) {
                $openingHourStart = $openingHour->getStart()->setDate($date->format('Y'), $date->format('m'), $date->format('d'));
                $openingHourEnd = $openingHour->getEnd()->setDate($date->format('Y'), $date->format('m'), $date->format('d'));
                return ($openingHourStart <= $date) && ($openingHourEnd >= $date);
            });

            // final check on closing exceptions
            if (count($openingHoursEnabledDayTime) > 0) {
                $closingExceptions = $this->em->getRepository(ClosingException::class)->findOngoing($date);
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
