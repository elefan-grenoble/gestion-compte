<?php
namespace App\Twig\Extension;

use App\Service\MembershipService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MembershipExtension extends AbstractExtension
{
    private $container;
    /** @var MembershipService $membershipService */
    private $membershipService;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->membershipService = $this->container->get('membership_service');
    }

    public function getFilters(): array
    {
        return array(
            new TwigFilter('can_register', array($this->membershipService, 'canRegister')),
            new TwigFilter('remainder', array($this->membershipService, 'getRemainder')),
            new TwigFilter('uptodate', array($this->membershipService, 'isUptodate')),
            new TwigFilter('expire', array($this->membershipService, 'getExpire'))
        );
    }
}
