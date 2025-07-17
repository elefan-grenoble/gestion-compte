<?php

namespace AppBundle\Service;

use DateTime;
use AppBundle\Entity\OpeningHour;
use Doctrine\ORM\EntityManagerInterface;

class OpeningHourKindService
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function hasEnabled()
    {
        $openingHourKindEnabled = $this->em->getRepository('AppBundle:OpeningHourKind')->findEnabled();
        return count($openingHourKindEnabled) > 0;
    }
}
