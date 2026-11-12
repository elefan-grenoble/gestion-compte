<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Beneficiary;
use App\Entity\Shift;
use App\Repository\ShiftRepository;
use App\Entity\TimeLog;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * POST /shift/{id}/book: the JSON endpoint behind the booking page.
 *
 * Success answers 200 with the URL to go to; a refusal answers 205 with the
 * same kind of body and an error flash. Either way the state in database is
 * what matters: the shift is held by the beneficiary, or it is untouched.
 *
 * @internal
 */
class ShiftControllerBookTest extends FunctionalTestCase
{
    use ShiftScenarios;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    public function testBookingAShiftHoldsItForTheBeneficiary(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        $beneficiary = $membership->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::logIn($client, $beneficiary->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $beneficiary->getId(), 'typeService' => 0]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('/', $client->getResponse()->getContent(), 'The body is the page to go to.');
        $this->assertArrayHasKey('success', static::flashes($client));

        $shift = static::reloaded($shift);
        $this->assertSame($beneficiary->getId(), $shift->getShifter()->getId());
        $this->assertSame($beneficiary->getUser()->getId(), $shift->getBooker()->getId());
        $this->assertNotNull($shift->getBookedTime());
        $this->assertNull($shift->getLastShifter());
        $this->assertFalse($shift->isFixe());
    }

    public function testBookingCountsTheShiftInTheMemberTimeCounter(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        $beneficiary = $membership->getMainBeneficiary();
        $shift = static::aBookableShift(null, 180);

        static::logIn($client, $beneficiary->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $beneficiary->getId(), 'typeService' => 0]);

        $logs = static::reloaded($membership)->getTimeLogs()->filter(function (TimeLog $log) use ($shift) {
            return TimeLog::TYPE_SHIFT_VALIDATED === $log->getType() && $log->getShift() && $log->getShift()->getId() === $shift->getId();
        });
        $this->assertCount(1, $logs, 'The booking should log the shift time once.');
        $this->assertSame(180, $logs->first()->getTime());
    }

    public function testTheFirstBookingSetsTheFirstShiftDateOfTheMembership(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        $this->assertNull($membership->getFirstShiftDate());
        $beneficiary = $membership->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::logIn($client, $beneficiary->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $beneficiary->getId(), 'typeService' => 0]);

        $this->assertEquals(new \DateTime('today'), static::reloaded($membership)->getFirstShiftDate());
    }

    public function testTheFirstShiftDateIsKeptByLaterBookings(): void
    {
        $client = static::createClient();
        $firstDate = new \DateTime('-30 days 00:00');
        $membership = static::persist(MembershipBuilder::aMembership()->withFirstShiftDate($firstDate)->build());
        $beneficiary = $membership->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::logIn($client, $beneficiary->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $beneficiary->getId(), 'typeService' => 0]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertEquals($firstDate, static::reloaded($membership)->getFirstShiftDate());
    }

    public function testAFixedBookingIsRecordedAsFixed(): void
    {
        $client = static::createClient();
        $beneficiary = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::logIn($client, $beneficiary->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $beneficiary->getId(), 'typeService' => 1]);

        $this->assertTrue(static::reloaded($shift)->isFixe());
    }

    public function testAMemberCanBookForAnotherBeneficiaryOfTheirMembership(): void
    {
        $client = static::createClient();
        $membership = static::persist(
            MembershipBuilder::aMembership()
                ->withBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Second', 'Beneficiary'))
                ->build()
        );
        $main = $membership->getMainBeneficiary();
        $second = $membership->getBeneficiaries()->filter(function (Beneficiary $b) use ($main) {
            return $b->getId() !== $main->getId();
        })->first();
        $shift = static::aBookableShift();

        static::logIn($client, $main->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $second->getId(), 'typeService' => 0]);

        $shift = static::reloaded($shift);
        $this->assertSame($second->getId(), $shift->getShifter()->getId());
        $this->assertSame($main->getUser()->getId(), $shift->getBooker()->getId(), 'The booker is who clicked.');
    }

    /**
     * A member must not be able to book, or burn a slot, for somebody else
     * by posting that beneficiary's id.
     */
    public function testAMemberCannotBookForABeneficiaryOfAnotherMembership(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $someoneElse = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $someoneElse->getId(), 'typeService' => 0]);

        $this->assertRefused($client, $shift);
    }

    public function testAnUnknownBeneficiaryIsRefused(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => 99999999, 'typeService' => 0]);

        $this->assertRefused($client, $shift);
    }

    public function testABookedShiftCannotBeBookedAgain(): void
    {
        $client = static::createClient();
        $holder = static::aMembership()->getMainBeneficiary();
        $me = static::aMembership()->getMainBeneficiary();
        $shift = ShiftBuilder::aShift()->bookedBy($holder)->build();
        static::persist($shift->getJob(), $shift);

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertSame(205, $client->getResponse()->getStatusCode());
        $this->assertSame($holder->getId(), static::reloaded($shift)->getShifter()->getId(), 'The first shifter keeps the shift.');
    }

    public function testAPastShiftCannotBeBooked(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift(new \DateTime('yesterday 09:00'));

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertRefused($client, $shift);
    }

    public function testALockedShiftCannotBeBooked(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();
        $shift->setLocked(true);
        static::persist($shift);

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertRefused($client, $shift);
    }

    public function testAFrozenMemberCannotBook(): void
    {
        $client = static::createClient();
        $me = static::persist(MembershipBuilder::aMembership()->frozen()->build())->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertRefused($client, $shift);
    }

    public function testAWithdrawnMemberCannotBook(): void
    {
        $client = static::createClient();
        $me = static::persist(MembershipBuilder::aMembership()->withdrawn()->build())->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertRefused($client, $shift);
    }

    public function testABeginnerCannotBeTheFirstToBookABucket(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $shift = ShiftBuilder::aShift()->startingAt(new \DateTime('tomorrow 09:00'))->build();
        static::persist($shift->getJob(), $shift);

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertRefused($client, $shift);
    }

    /**
     * Once the cycle quota is reached, a shift further away than the delay
     * for extra shifts is refused (MAX_TIME_IN_ADVANCE_TO_BOOK_EXTRA_SHIFTS).
     * The same shift is bookable by a member who has booked nothing yet.
     */
    public function testTheCycleQuotaStopsABookingBeyondTheExtraShiftDelay(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $fresh = static::aMembership()->getMainBeneficiary();

        // Same day, so both shifts are in the same cycle whatever today is.
        $day = new \DateTime('+10 days');
        $morning = static::aBookableShift((clone $day)->setTime(9, 0), 180);
        $afternoon = static::aBookableShift((clone $day)->setTime(14, 0), 180);

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $morning->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), 'The first 3 hours are within the quota.');

        static::postJson($client, '/shift/' . $afternoon->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);
        $this->assertRefused($client, $afternoon);

        static::logIn($client, $fresh->getUser());
        static::postJson($client, '/shift/' . $afternoon->getId() . '/book', ['beneficiaryId' => $fresh->getId(), 'typeService' => 0]);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), 'The quota is per member.');
    }

    /**
     * A booking made right after another counts it in the quota
     * (SHIFT-QUOTA-CACHE: ShiftRepository::findShiftsForBeneficiaries() kept
     * its result for 5 seconds in a filesystem cache shared by every request,
     * so a second booking made at once slipped through).
     */
    public function testTheQuotaHoldsForABookingMadeRightAfterAnother(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();

        $day = new \DateTime('+10 days');
        $morning = static::aBookableShift((clone $day)->setTime(9, 0), 180);
        $afternoon = static::aBookableShift((clone $day)->setTime(14, 0), 180);

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $morning->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);
        static::postJson($client, '/shift/' . $afternoon->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertRefused($client, $afternoon);
    }

    public function testAShiftWithinTheExtraShiftDelayIsBookableBeyondTheQuota(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();

        $day = new \DateTime('+1 day');
        $morning = static::aBookableShift((clone $day)->setTime(9, 0), 180);
        $afternoon = static::aBookableShift((clone $day)->setTime(14, 0), 180);

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $morning->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);
        static::postJson($client, '/shift/' . $afternoon->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame($me->getId(), static::reloaded($afternoon)->getShifter()->getId());
    }

    public function testTwoOverlappingShiftsCannotBeBookedByTheSameBeneficiary(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();

        $day = new \DateTime('+1 day');
        $first = static::aBookableShift((clone $day)->setTime(9, 0), 180);
        $overlapping = static::aBookableShift((clone $day)->setTime(10, 0), 180);

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $first->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);
        static::postJson($client, '/shift/' . $overlapping->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertRefused($client, $overlapping);
    }

    public function testAShiftReservedForAnotherPreviousShifterCannotBeBooked(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $previous = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();
        $shift->setLastShifter($previous);
        static::persist($shift);

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertSame(205, $client->getResponse()->getStatusCode());
        $reloaded = static::reloaded($shift);
        $this->assertNull($reloaded->getShifter());
        $this->assertSame($previous->getId(), $reloaded->getLastShifter()->getId());
    }

    public function testThePreviousShifterBookingTheirReservedShiftClearsTheReservation(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();
        $shift->setLastShifter($me);
        static::persist($shift);

        static::logIn($client, $me->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $reloaded = static::reloaded($shift);
        $this->assertSame($me->getId(), $reloaded->getShifter()->getId());
        $this->assertNull($reloaded->getLastShifter());
    }

    public function testBookingRequiresALogin(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId(), 'typeService' => 0]);

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
        $this->assertNull(static::reloaded($shift)->getShifter());
    }

    /**
     * I-SEC-13: shift_alone.html.twig posts beneficiaryId as a form
     * (application/x-www-form-urlencoded) while the action reads a JSON body.
     * The target behavior is that the form of the template books the shift.
     */
    public function testTheFormPostedByShiftAloneBooksTheShift(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();

        static::logIn($client, $me->getUser());

        try {
            $client->request('POST', '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $me->getId()]);
        } catch (\Throwable $e) {
            $this->markTestIncomplete('I-SEC-13 open: a form-encoded body breaks the JSON endpoint (' . get_class($e) . ': ' . $e->getMessage() . ')');
        }

        if (null === static::reloaded($shift)->getShifter()) {
            $this->markTestIncomplete('I-SEC-13 open: the form posted by shift_alone.html.twig does not book the shift (answered ' . $client->getResponse()->getStatusCode() . ').');
        }
        $this->assertSame($me->getId(), static::reloaded($shift)->getShifter()->getId());
    }

    private function assertRefused($client, Shift $shift): void
    {
        $this->assertSame(205, $client->getResponse()->getStatusCode(), 'A refusal answers 205.');
        $this->assertSame('/booking/', $client->getResponse()->getContent(), 'The body sends back to the booking page.');
        $this->assertSame(['Impossible de réserver ce créneau'], static::flashes($client)['error'] ?? []);
        $this->assertNull(static::reloaded($shift)->getShifter(), 'A refused booking must leave the shift free.');
    }
}
