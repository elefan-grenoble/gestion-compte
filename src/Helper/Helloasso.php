<?php

namespace App\Helper;

class Helloasso {

    private $apiBaseUrl;
    private $apiKey;
    private $helloAssoApiPassword;

    public function __construct($helloAssoApiBaseUrl, $helloAssoApiKey, $helloAssoApiPassword) {
        $this->apiBaseUrl = $helloAssoApiBaseUrl;
        $this->apiKey = $helloAssoApiKey;
        $this->helloAssoApiPassword = $helloAssoApiPassword;
    }

    public function get($key,$params = array()){
        $url = $this->apiBaseUrl."$key.json";
        if ($params)
            $url = $url . "?" . http_build_query($params);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->apiKey . ":" . $this->helloAssoApiPassword);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $json = json_decode(curl_exec($curl));
        curl_close($curl);
        return $json;
    }

}