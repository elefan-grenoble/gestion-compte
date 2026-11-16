<?php

namespace App\Tests\Unit\Event;

use App\Entity\Shift;
use App\Event\ShiftBookedEvent;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ShiftBookedEventTest extends TestCase
{
    public function testExposesTheShift(): void
    {
        $shift = new Shift();

        $this->assertSame($shift, (new ShiftBookedEvent($shift, false))->getShift());
    }

    /**
     * @dataProvider fromAdminProvider
     */
    public function testTellsWhetherTheBookingCameFromAnAdmin(bool $fromAdmin): void
    {
        $this->assertSame($fromAdmin, (new ShiftBookedEvent(new Shift(), $fromAdmin))->isFromAdmin());
    }

    public function fromAdminProvider(): array
    {
        return ['from an admin' => [true], 'from the member' => [false]];
    }
}
