<?php
namespace App\Twig\Extension;

use App\Entity\AbstractRegistration;
use App\Entity\AnonymousBeneficiary;
use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Registration;
use App\Entity\Shift;
use App\Entity\SwipeCard;
use App\Service\MembershipService;
use App\Service\Picture\BasePathPicture;
use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use CodeItNow\BarcodeBundle\Utils\QrCode;
use DateInterval;
use App\Entity\Task;
use Michelf\Markdown;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class MembershipExtension extends AbstractExtension
{

    private $container;
    /** @var MembershipService $membershipService */
    private $membershipService;

    public function __construct(Container $container) {
        $this->container = $container;
        $this->membershipService = $this->container->get('membership_service');
    }

    public function getFilters()
    {
        return array(
            new TwigFilter('can_register',array($this->membershipService,'canRegister')),
            new TwigFilter('remainder',array($this->membershipService,'getRemainder')),
            new TwigFilter('uptodate',array($this->membershipService,'isUptodate')),
            new TwigFilter('expire',array($this->membershipService,'getExpire'))
        );
    }
}
