<?php

namespace App\Tests\Unit\Helper;

use App\Helper\QrCodePng;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class QrCodePngTest extends TestCase
{
    /**
     * Badges and the `qr` Twig filter share this PNG (C-BUG-7: both called
     * the endroid/qr-code 3 API while version 4 is installed).
     */
    public function testRendersASquarePng(): void
    {
        $png = QrCodePng::fromText('https://example.org/sw/in/abc');

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $this->assertSame([QrCodePng::SIZE, QrCodePng::SIZE], array_slice(getimagesizefromstring($png), 0, 2));
    }
}
