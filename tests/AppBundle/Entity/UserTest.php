<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Twig\Extension\AppExtension;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{

    protected $beneficiary;

    protected $user;

    protected $appExt;

    public function setUp()
    {
        $this->user = new User();
        $this->beneficiary = new Beneficiary();
        $this->user->addBeneficiary($this->beneficiary);

        $this->appExt = new AppExtension();
    }

    public function fixture_bookOneShift($beneficiary, $start, $end)
    {
        $shift = new Shift();
        $shift->setStart(date_create($start));
        $shift->setEnd(date_create($end));
        $beneficiary->addShift($shift);
    }

    public function test_remainingToBook_no_booked_shift_for_current_cycle()
    {
        $remaining = $this->user->remainingToBook();
        $this->assertEquals(180, $remaining);
        $this->assertEquals("3h00", $this->appExt->duration_from_minutes($remaining));
    }

    public function test_remainingToBook_all_done_for_current_cycle()
    {
        $this->user->setFirstShiftDate(date_create("2018-01-01"));
        $this->fixture_bookOneShift($this->beneficiary, "2018-05-10 12:00", "2018-05-10 15:00");

        $remaining = $this->user->remainingToBook();
        $this->assertEquals(0, $remaining);
        $this->assertEquals("0h00", $this->appExt->duration_from_minutes($remaining));
    }

    public function test_remainingToBook_half_done_for_current_cycle()
    {
        $this->user->setFirstShiftDate(date_create("2018-01-01"));
        $this->fixture_bookOneShift($this->beneficiary, "2018-05-10 14:00", "2018-05-10 15:30");

        $remaining = $this->user->remainingToBook();
        $this->assertEquals(90, $remaining);
        $this->assertEquals("1h30", $this->appExt->duration_from_minutes($remaining));
    }

    public function test_duration_from_minutes_independent_from_timezone()
    {
        $minutes = 240;

        date_default_timezone_set("Europe/Paris");
        $this->assertEquals("4h00", $this->appExt->duration_from_minutes($minutes));

        date_default_timezone_set("Europe/Moscow");
        $this->assertEquals("4h00", $this->appExt->duration_from_minutes($minutes));
    }


    // Tests for startOfCycle
}
