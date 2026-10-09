<?php

namespace App\Helper;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * PNG QR codes of the badges and of the `qr` Twig filter (endroid/qr-code 4).
 */
class QrCodePng
{
    public const SIZE = 200;

    /**
     * @return string the PNG image
     */
    public static function fromText(string $text): string
    {
        $qrCode = QrCode::create($text)
            ->setSize(self::SIZE)
            ->setMargin(0)
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255))
            ->setEncoding(new Encoding('UTF-8'))
        ;

        return (new PngWriter())->write($qrCode)->getString();
    }
}
