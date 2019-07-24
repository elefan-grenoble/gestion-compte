<?php
namespace AppBundle\Twig\Extension;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\ProcessUpdate;
use AppBundle\Entity\Shift;
use Symfony\Component\DependencyInjection\Container;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ProcessUpdateExtension extends AbstractExtension
{

    private $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function getFilters()
    {
        return array(
            new TwigFilter('last_shift_date',array($this,'last_shift_date')),
            new TwigFilter('updates_list_from_date',array($this,'updates_list_from_date'))
        );
    }

    public function last_shift_date(Beneficiary $beneficiary){
        return $this->container->get('doctrine')->getManager()->getRepository(Shift::class)->findLastShifted($beneficiary)->getStart();
    }

    public function updates_list_from_date(\DateTime $date){
        return $this->container->get('doctrine')->getManager()->getRepository(ProcessUpdate::class)->findFrom($date);
    }
}
