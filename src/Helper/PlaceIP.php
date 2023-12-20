<?php

namespace App\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class PlaceIP {

    private $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    /**
     * If enable_place_local_ip_address_check is true & place_local_ip_address is set
     * Then we check the client's IP against the IP list
     */
    public function isLocationOk()
    {
        $checkIps = $this->container->getParameter('enable_place_local_ip_address_check');

        if (isset($checkIps) and $checkIps) {
            $whitelist_ips = $this->container->getParameter('place_local_ip_address');
            $whitelist_ips = explode(',', $whitelist_ips);
            $current_ip = $this->container->get('request_stack')->getCurrentRequest()->getClientIp();

            $current_ip_in_whitelist_ips = array_filter($whitelist_ips, function($whitelist_ip) use ($current_ip) {
                return str_starts_with($current_ip, $whitelist_ip);
            });
            if (count($current_ip_in_whitelist_ips)) {
                return True;
            }
        }

        return False;
    }
}
