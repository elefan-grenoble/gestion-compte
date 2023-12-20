<?php
namespace App\Twig\Extension;

use App\Entity\AbstractRegistration;
use App\Entity\Beneficiary;
use App\Service\BeneficiaryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BeneficiaryExtension extends AbstractExtension
{
    private $container;
    private $beneficiaryService;

    public function __construct(ContainerInterface $container) {
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
