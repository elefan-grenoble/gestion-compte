<?php

namespace App\Service;

use DateTime;
use App\Entity\OpeningHour;
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
        $openingHourKindEnabled = $this->em->getRepository('App:OpeningHourKind')->findEnabled();
        return count($openingHourKindEnabled) > 0;
    }
}
