<?php

namespace App\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class Helloasso {

    private $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function get($key,$params = array()){
        $url = $this->container->getParameter('helloasso_api_base_url')."$key.json";
        if ($params)
            $url = $url . "?" . http_build_query($params);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->container->getParameter('helloasso_api_key') . ":" . $this->container->getParameter('helloasso_api_password'));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $json = json_decode(curl_exec($curl));
        curl_close($curl);
        return $json;
    }

}