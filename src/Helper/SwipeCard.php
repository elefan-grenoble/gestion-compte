<?php

namespace App\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class SwipeCard {

    const PADLENGTH = 8;

    private $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    private function getKey($length){
        $key = $this->container->getParameter('secret');
        if (strlen($key) >= $length){
            return substr($key,0,$length);
        }else{
            return str_pad('', $length, $key);
        }
    }

    //Chiffre_de_Vigenère
    public function vigenereEncode($string){
        $return = str_pad('', strlen($string), ' ', STR_PAD_LEFT);
        $key = $this->getKey(strlen($string));
        for ( $pos=0; $pos < strlen($string); $pos ++ ) {
            $return[$pos] = chr((ord($string[$pos]) + ord($key[$pos])) % 256);
        }
        return base64_encode($return);
    }

    public function vigenereDecode($string){
        $string = base64_decode($string);
        $return = str_pad('', strlen($string), ' ', STR_PAD_LEFT);
        $key = $this->getKey(strlen($string));
        for ( $pos=0; $pos < strlen($string); $pos ++ ) {
            $return[$pos] = chr((ord($string[$pos]) - ord($key[$pos])) % 256);
        }
        return $return;
    }

    static public function generateCode(){
        $code = rand(0,pow(10,self::PADLENGTH));
        $code = str_pad($code, self::PADLENGTH, '0', STR_PAD_LEFT);
        return $code;
    }
}