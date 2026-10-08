<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Beneficiary;
use App\Entity\Shift;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * A shift reserved for the member who held it last cycle (lastShifter): the
 * member accepts it, which books it, or rejects it, which frees it for all.
 *
 * The anonymous link with a token and the GET-without-CSRF weakness are
 * pinned by Security\ShiftSecurityTest (I-SEC-3).
 *
 * @internal
 */
class ShiftControllerReservationTest extends FunctionalTestCase
{
    use ShiftScenarios;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    public function testAcceptingBooksTheShiftForTheMember(): void
    {
        $client = static::createClient();
        [$shift, $previous] = $this->aShiftReservedFor();
        static::logIn($client, $previous->getUser());

        $client->request('GET', '/shift/' . $shift->getId() . '/accept');

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertSame(['Créneau réservé ! Merci ' . $previous->getFirstname()], static::flashes($client)['success'] ?? []);
        $shift = static::reloaded($shift);
        $this->assertSame($previous->getId(), $shift->getShifter()->getId());
        $this->assertSame($previous->getUser()->getId(), $shift->getBooker()->getId());
        $this->assertNull($shift->getLastShifter(), 'The reservation is consumed.');
        $this->assertFalse($shift->isFixe());
        $this->assertNotNull($shift->getBookedTime());
    }

    public function testAcceptingLogsTheShiftTimeOfTheMember(): void
    {
        $client = static::createClient();
        [$shift, $previous] = $this->aShiftReservedFor();
        static::logIn($client, $previous->getUser());

        $client->request('GET', '/shift/' . $shift->getId() . '/accept');

        $this->assertSame(180, static::reloaded($previous->getMembership())->getShiftTimeCount());
    }

    public function testRejectingFreesTheReservationForEveryone(): void
    {
        $client = static::createClient();
        [$shift, $previous] = $this->aShiftReservedFor();
        static::logIn($client, $previous->getUser());

        $client->request('GET', '/shift/' . $shift->getId() . '/reject');

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $flashes = static::flashes($client);
        $this->assertSame(['Créneau libéré !'], $flashes['success'] ?? []);
        $this->assertCount(1, $flashes['warning'] ?? []);
        $shift = static::reloaded($shift);
        $this->assertNull($shift->getLastShifter());
        $this->assertNull($shift->getShifter(), 'Rejecting does not book anything.');
    }

    public function testAMemberWhoIsNotTheReservedOneCannotAccept(): void
    {
        $client = static::createClient();
        [$shift, $previous] = $this->aShiftReservedFor();
        $other = static::aMembership()->getMainBeneficiary();
        static::logIn($client, $other->getUser());

        $client->request('GET', '/shift/' . $shift->getId() . '/accept');

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertSame(["Impossible d'accepter la réservation"], static::flashes($client)['error'] ?? []);
        $shift = static::reloaded($shift);
        $this->assertNull($shift->getShifter());
        $this->assertSame($previous->getId(), $shift->getLastShifter()->getId(), 'The reservation is untouched.');
    }

    public function testAMemberWhoIsNotTheReservedOneCannotReject(): void
    {
        $client = static::createClient();
        [$shift, $previous] = $this->aShiftReservedFor();
        $other = static::aMembership()->getMainBeneficiary();
        static::logIn($client, $other->getUser());

        $client->request('GET', '/shift/' . $shift->getId() . '/reject');

        $this->assertSame(['Impossible de rejeter la réservation'], static::flashes($client)['error'] ?? []);
        $this->assertSame($previous->getId(), static::reloaded($shift)->getLastShifter()->getId());
    }

    public function testAShiftManagerCanAcceptOnBehalfOfTheReservedMember(): void
    {
        $client = static::createClient();
        [$shift, $previous] = $this->aShiftReservedFor();
        $manager = static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser(UserBuilder::aUser()->withRoles('ROLE_SHIFT_MANAGER')))
                ->build()
        )->getMainBeneficiary()->getUser();
        static::logIn($client, $manager);

        $client->request('GET', '/shift/' . $shift->getId() . '/accept');

        $shift = static::reloaded($shift);
        $this->assertSame($previous->getId(), $shift->getShifter()->getId(), 'The shifter is the reserved member, not the manager.');
        $this->assertSame($manager->getId(), $shift->getBooker()->getId());
    }

    public function testADecisionAlreadyTakenCannotBeTakenAgain(): void
    {
        $client = static::createClient();
        [$shift, $previous] = $this->aShiftReservedFor();
        static::logIn($client, $previous->getUser());
        $client->request('GET', '/shift/' . $shift->getId() . '/reject');

        $client->request('GET', '/shift/' . $shift->getId() . '/accept');

        $this->assertSame(['Oups, ce créneau a déjà été confirmé / refusé, ou le délai de reservation est écoulé.'], static::flashes($client)['error'] ?? []);
        $this->assertNull(static::reloaded($shift)->getShifter());
    }

    public function testAnUnknownShiftIs404(): void
    {
        $client = static::createClient();
        $member = static::aMembership()->getMainBeneficiary();
        static::logIn($client, $member->getUser());

        $client->request('GET', '/shift/99999999/accept');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    /**
     * A free shift of tomorrow, in a bucket another member already holds,
     * reserved for the member who held it last cycle.
     *
     * @return array{Shift, Beneficiary}
     */
    private function aShiftReservedFor(): array
    {
        $previous = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();
        $shift->setLastShifter($previous);
        static::persist($shift);
        static::entityManager()->clear();

        return [$shift, $previous];
    }
}
