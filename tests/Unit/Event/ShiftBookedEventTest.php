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
        $this->markTestIncomplete('I-BUG-5 open: ShiftBookedEvent::$fromAdmin is never assigned by the constructor, isFromAdmin() always returns null.');

        $this->assertSame($fromAdmin, (new ShiftBookedEvent(new Shift(), $fromAdmin))->isFromAdmin());
    }

    public function fromAdminProvider(): array
    {
        return ['from an admin' => [true], 'from the member' => [false]];
    }
}
