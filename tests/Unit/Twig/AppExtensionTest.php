<?php

namespace App\Tests\Unit\Twig;

use App\Helper\SwipeCard;
use App\Service\Picture\BasePathPicture;
use App\Twig\Extension\AppExtension;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
class AppExtensionTest extends TestCase
{
    private function createExtension(): AppExtension
    {
        return new AppExtension(
            $this->createMock(BasePathPicture::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
            'monnaie',
            $this->createMock(SwipeCard::class)
        );
    }

    /**
     * @dataProvider phoneProvider
     */
    public function testToPhoneGroups(?string $phone, string $expected): void
    {
        $this->assertSame($expected, $this->createExtension()->toPhoneGroups($phone));
    }

    public function phoneProvider(): array
    {
        return [
            'numéro à 10 chiffres' => ['0607080910', '06 07 08 09 10'],
            'déjà espacé' => ['06 07 08 09 10', '06 07 08 09 10'],
            'avec points' => ['06.07.08.09.10', '06 07 08 09 10'],
            'international collé' => ['+33607080910', '+33 6 07 08 09 10'],
            'international espacé' => ['+33 6 07 08 09 10', '+33 6 07 08 09 10'],
            'international avec points' => ['+33.6.07.08.09.10', '+33 6 07 08 09 10'],
            'international avec 0 en trop' => ['+330607080910', '+330607080910'],
            'Réunion' => ['+262692123456', '+262 692 12 34 56'],
            'Guadeloupe espacé' => ['+590 690 12 34 56', '+590 690 12 34 56'],
            'Martinique avec points' => ['+596.696.12.34.56', '+596 696 12 34 56'],
            'Guyane' => ['+594694123456', '+594 694 12 34 56'],
            'DROM national' => ['0692123456', '06 92 12 34 56'],
            'Suisse' => ['+41791234567', '+41 79 123 45 67'],
            'Suisse espacé' => ['+41 79 123 45 67', '+41 79 123 45 67'],
            'Belgique mobile' => ['+32470123456', '+32 470 12 34 56'],
            'Belgique fixe (laissé tel quel)' => ['+3221234567', '+3221234567'],
            'Nouvelle-Calédonie (laissé tel quel)' => ['+687123456', '+687123456'],
            'autre pays' => ['+14155550123', '+14155550123'],
            '10 chiffres sans 0 initial' => ['6070809101', '6070809101'],
            'trop court' => ['0607', '0607'],
            'texte libre avec point' => ['voir avec Marie.', 'voir avec Marie.'],
            'vide' => ['', ''],
            'null' => [null, ''],
        ];
    }
}
