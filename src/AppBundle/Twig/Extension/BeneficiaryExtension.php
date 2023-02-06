<?php
namespace AppBundle\Twig\Extension;

use AppBundle\Entity\AbstractRegistration;
use AppBundle\Entity\Beneficiary;
use AppBundle\Service\BeneficiaryService;
use Symfony\Component\DependencyInjection\Container;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BeneficiaryExtension extends AbstractExtension
{
    private $container;
    private $beneficiaryService;

    public function __construct(Container $container) {
        $this->container = $container;
        $this->beneficiaryService = $this->container->get('beneficiary_service');
    }

    public function getFilters()
    {
        return array(
            new TwigFilter('print_with_number_and_status_icon', array($this->beneficiaryService, 'getDisplayNameWithMemberNumberAndStatusIcon')),
        );
    }

}
