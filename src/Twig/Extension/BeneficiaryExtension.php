<?php

namespace App\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class BeneficiaryExtension extends AbstractExtension
{
    private $container;
    private $beneficiaryService;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->beneficiaryService = $this->container->get('beneficiary_service');
    }

    public function getFilters()
    {
        return [
            new TwigFilter('print_with_number_and_status_icon', [$this->beneficiaryService, 'getDisplayNameWithMemberNumberAndStatusIcon']),
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('is_new_beneficiary', [$this->beneficiaryService, 'isNew']),
        ];
    }
}
