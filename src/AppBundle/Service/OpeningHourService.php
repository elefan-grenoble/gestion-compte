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
        $openingHours = $this->em->getRepository('AppBundle:OpeningHour')->findAll();

        $openingHoursDay = array_filter($openingHours, function($openingHour) use ($date) {
            return $openingHour->getDayOfWeek() == ($date->format('N') - 1);
        });
        if (count($openingHoursDay) > 0) {
            $openingHoursDayTime = array_filter($openingHoursDay, function($openingHour) use ($date) {
                $openingHourStart = $openingHour->getStart()->setDate($date->format('Y'), $date->format('m'), $date->format('d'));
                $openingHourEnd = $openingHour->getEnd()->setDate($date->format('Y'), $date->format('m'), $date->format('d'));
                return ($openingHourStart <= $date) && ($openingHourEnd >= $date);
            });
            if (count($openingHoursDayTime) > 0) {
                return True;
            }
        }

        return False;
    }

    public function isClosed(\DateTime $date = null)
    {
        return !$self->isOpen($date);
    }
}
