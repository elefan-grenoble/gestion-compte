<?php

namespace App\Twig\Extension;

use App\Service\MembershipService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MembershipExtension extends AbstractExtension
{
    private $container;

    /** @var MembershipService */
    private $membershipService;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->membershipService = $this->container->get('membership_service');
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('can_register', [$this->membershipService, 'canRegister']),
            new TwigFilter('remainder', [$this->membershipService, 'getRemainder']),
            new TwigFilter('uptodate', [$this->membershipService, 'isUptodate']),
            new TwigFilter('expire', [$this->membershipService, 'getExpire']),
        ];
    }
}
