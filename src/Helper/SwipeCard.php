<?php

namespace App\Helper;

class SwipeCard
{
    public const PADLENGTH = 8;

    private string $swipeCardSecret;

    public function __construct(string $swipeCardSecret)
    {
        $this->swipeCardSecret = $swipeCardSecret;
    }

    private function getKey($length)
    {
        $key = $this->swipeCardSecret;
        if (strlen($key) >= $length) {
            return substr($key, 0, $length);
        }

        return str_pad('', $length, $key);

    }

    // Chiffre_de_Vigenère
    public function vigenereEncode($string)
    {
        $return = str_pad('', strlen($string), ' ', STR_PAD_LEFT);
        $key = $this->getKey(strlen($string));
        for ($pos = 0; $pos < strlen($string); ++$pos ) {
            $return[$pos] = chr((ord($string[$pos]) + ord($key[$pos])) % 256);
        }

        return base64_encode($return);
    }

    public function vigenereDecode($string)
    {
        $string = base64_decode($string);
        $return = str_pad('', strlen($string), ' ', STR_PAD_LEFT);
        $key = $this->getKey(strlen($string));
        for ($pos = 0; $pos < strlen($string); ++$pos ) {
            $return[$pos] = chr((ord($string[$pos]) - ord($key[$pos])) % 256);
        }

        return $return;
    }

    public static function generateCode()
    {
        $code = rand(0, pow(10, self::PADLENGTH));

        return str_pad($code, self::PADLENGTH, '0', STR_PAD_LEFT);
    }
}
