<?php
namespace App\Twig\Extension;

use App\Entity\Beneficiary;
use App\Entity\ProcessUpdate;
use App\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ProcessUpdateExtension extends AbstractExtension
{


    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getFilters()
    {
        return array(
            new TwigFilter('last_shift_date',array($this,'last_shift_date')),
            new TwigFilter('updates_list_from_date',array($this,'updates_list_from_date')),
            new TwigFilter('count_updates_list_from_date',array($this,'count_updates_list_from_date')),
            new TwigFilter('w3c_to_date',array($this,'w3c_to_date'))
        );
    }

    public function last_shift_date(Beneficiary $beneficiary){
        $lastShifted = $this->entityManager->getRepository(Shift::class)->findLastShifted($beneficiary);
        if ($lastShifted)
            return $lastShifted->getStart();
        else
            return $beneficiary->getUser()->getLastLogin();
    }

    public function updates_list_from_date(\DateTime $date){
        return $this->entityManager->getRepository(ProcessUpdate::class)->findFrom($date);
    }

    public function count_updates_list_from_date(\DateTime $date){
        return $this->entityManager->getRepository(ProcessUpdate::class)->countFrom($date);
    }

    public function w3c_to_date($w3c){
        return \DateTime::createFromFormat(\DateTimeInterface::W3C,$w3c);
    }
}
