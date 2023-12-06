<?php

namespace AppBundle\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class PlaceIP {

    private $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function isLocationOk()
    {
        $ip = $this->container->get('request_stack')->getCurrentRequest()->getClientIp();
        $checkIps = $this->container->getParameter('enable_place_local_ip_address_check');
        $ips = $this->container->getParameter('place_local_ip_address');
        $ips = explode(',', $ips);

        return (isset($checkIps) and !$checkIps) or (isset($ip) and in_array($ip, $ips));
    }
}
