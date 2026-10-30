<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Address;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class AddressTest extends TestCase
{
    public function testNewAddressIsBlank(): void
    {
        $this->assertTrue((new Address())->isBlank());
    }

    public function testAddressWithOnlyEmptyOrSpaceValuesIsBlank(): void
    {
        $address = new Address();
        $address->setStreet1('');
        $address->setStreet2('  ');
        $address->setZipcode('');
        $address->setCity(null);

        $this->assertTrue($address->isBlank());
    }

    public function testAddressWithAtLeastOneValueIsNotBlank(): void
    {
        $address = new Address();
        $address->setCity('Grenoble');

        $this->assertFalse($address->isBlank());
    }
}
