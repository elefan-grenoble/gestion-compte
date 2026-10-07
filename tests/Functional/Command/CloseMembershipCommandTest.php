<?php

namespace App\Tests\Functional\Command;

use App\Entity\Membership;
use App\Tests\Support\Builder\MembershipBuilder;

/**
 * app:member:close withdraws the memberships whose last registration expired
 * more than the given delay ago.
 *
 * With the test configuration a registration lasts 1 year, so with a delay of
 * "1 month" a membership is closed once its last registration is more than
 * 1 year, 1 month and 1 day old. The dates below are built from today.
 *
 * @internal
 */
class CloseMembershipCommandTest extends CommandTestCase
{
    private const DELAY = '1 month';

    public function testClosesTheMembershipsWhoseRegistrationExpiredBeforeTheDelay(): void
    {
        $expired = $this->membershipRegisteredOn(self::cutoff()->modify('-1 day'));

        $tester = $this->runCommand('app:member:close', ['delay' => self::DELAY]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Close membership #' . $expired->getMemberNumber(), $tester->getDisplay());
        $this->assertStringContainsString('1 membership(s) closed', $tester->getDisplay());

        $expired = $this->reload($expired);
        $this->assertTrue($expired->getWithdrawn());
        $this->assertSame((new \DateTime('today'))->format('Y-m-d'), $expired->getWithdrawnDate()->format('Y-m-d'));
    }

    public function testClosesAMembershipRegisteredExactlyOnTheCutoffDay(): void
    {
        $onTheEdge = $this->membershipRegisteredOn(self::cutoff());

        $this->runCommand('app:member:close', ['delay' => self::DELAY]);

        $this->assertTrue($this->reload($onTheEdge)->getWithdrawn());
    }

    public function testLeavesTheMembershipsWhoseRegistrationHasNotExpiredYet(): void
    {
        $justInTime = $this->membershipRegisteredOn(self::cutoff()->modify('+1 day'));
        $registeredToday = $this->membershipRegisteredOn(new \DateTime('today'));
        // Expired for the registration duration, but still within the delay.
        $expiredWithinDelay = $this->membershipRegisteredOn(new \DateTime('-1 year -2 days'));

        $tester = $this->runCommand('app:member:close', ['delay' => self::DELAY]);

        $this->assertStringContainsString('0 membership(s) closed', $tester->getDisplay());
        foreach ([$justInTime, $registeredToday, $expiredWithinDelay] as $membership) {
            $membership = $this->reload($membership);
            $this->assertFalse($membership->getWithdrawn(), 'membership #' . $membership->getMemberNumber());
            $this->assertNull($membership->getWithdrawnDate());
        }
    }

    public function testOnlyTheLastRegistrationCounts(): void
    {
        $renewed = $this->membershipRegisteredOn(self::cutoff()->modify('-3 years'), new \DateTime('-2 months'));

        $tester = $this->runCommand('app:member:close', ['delay' => self::DELAY]);

        $this->assertStringContainsString('0 membership(s) closed', $tester->getDisplay());
        $this->assertFalse($this->reload($renewed)->getWithdrawn());
    }

    public function testDoesNotTouchTheMembershipsAlreadyWithdrawn(): void
    {
        $date = new \DateTime('2020-03-04');
        $withdrawn = MembershipBuilder::aMembership()->withdrawn()->registeredOn(self::cutoff()->modify('-2 years'))->build();
        $withdrawn->setWithdrawnDate($date);
        static::persist($withdrawn);

        $tester = $this->runCommand('app:member:close', ['delay' => self::DELAY]);

        $this->assertStringContainsString('0 membership(s) closed', $tester->getDisplay());
        $this->assertSame('2020-03-04', $this->reload($withdrawn)->getWithdrawnDate()->format('Y-m-d'));
    }

    public function testUnfreezesTheMembershipItCloses(): void
    {
        $frozen = MembershipBuilder::aMembership()->frozen()->registeredOn(self::cutoff()->modify('-1 day'))->build();
        static::persist($frozen);

        $this->runCommand('app:member:close', ['delay' => self::DELAY]);

        $frozen = $this->reload($frozen);
        $this->assertTrue($frozen->getWithdrawn());
        $this->assertFalse($frozen->getFrozen(), 'a withdrawn membership is not frozen anymore');
    }

    public function testClosesOnlyTheMembershipsThatExpired(): void
    {
        $expired = [
            $this->membershipRegisteredOn(self::cutoff()->modify('-1 day')),
            $this->membershipRegisteredOn(self::cutoff()->modify('-5 years')),
        ];
        $active = $this->membershipRegisteredOn(new \DateTime('-6 months'));

        $tester = $this->runCommand('app:member:close', ['delay' => self::DELAY]);

        $this->assertStringContainsString('2 membership(s) closed', $tester->getDisplay());
        foreach ($expired as $membership) {
            $this->assertTrue($this->reload($membership)->getWithdrawn());
        }
        $this->assertFalse($this->reload($active)->getWithdrawn());
    }

    public function testDelayIsAddedToTheRegistrationDuration(): void
    {
        $membership = $this->membershipRegisteredOn(new \DateTime('-1 year -2 months'));

        $this->runCommand('app:member:close', ['delay' => '3 months']);
        $this->assertFalse($this->reload($membership)->getWithdrawn(), 'within a delay of 3 months');

        $this->runCommand('app:member:close', ['delay' => '1 month']);
        $this->assertTrue($this->reload($membership)->getWithdrawn(), 'beyond a delay of 1 month');
    }

    public function testClosesAtTheEndOfTheCivilYearWhenRegistrationsFollowTheCivilYear(): void
    {
        $this->withEnv('REGISTRATION_EVERY_CIVIL_YEAR', 'true');

        // With a delay of 1 month, the registrations of year Y expire on
        // December 31st of Y, once Y + 1 and 1 month have gone by.
        $year = (int) (new \DateTime('-1 year -' . self::DELAY))->format('Y');
        $lastDayOfTheYear = $this->membershipRegisteredOn(new \DateTime($year . '-12-31'));
        $firstDayOfTheNextYear = $this->membershipRegisteredOn(new \DateTime(($year + 1) . '-01-01'));

        $this->runCommand('app:member:close', ['delay' => self::DELAY]);

        $this->assertTrue($this->reload($lastDayOfTheYear)->getWithdrawn());
        $this->assertFalse($this->reload($firstDayOfTheNextYear)->getWithdrawn());
    }

    public function testRecordsWhoClosedTheMembership(): void
    {
        $this->markTestIncomplete('I-BUG-11 open: CloseMembershipCommand never sets withdrawnBy (see the TODO in the command).');

        $expired = $this->membershipRegisteredOn(self::cutoff()->modify('-1 day'));

        $this->runCommand('app:member:close', ['delay' => self::DELAY]);

        // Target behaviour: a closure by the cron is told apart from an
        // admin's, whether by a system account or by an explicit marker.
        $this->assertNotNull($this->reload($expired)->getWithdrawnBy());
    }

    /**
     * The last day of registration that app:member:close closes for the
     * default configuration (registration of 1 year) and DELAY.
     */
    private static function cutoff(): \DateTime
    {
        return new \DateTime('today -1 year -1 day -' . self::DELAY);
    }

    private function membershipRegisteredOn(\DateTime ...$dates): Membership
    {
        return static::persist(MembershipBuilder::aMembership()->registeredOn(...$dates)->build());
    }

    private function reload(Membership $membership): Membership
    {
        $id = $membership->getId();
        $em = static::entityManager();
        $em->clear();

        return $em->find(Membership::class, $id);
    }
}
