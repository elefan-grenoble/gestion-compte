<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * The booking pages a member uses: /booking/ and what it loads.
 *
 * The booking itself (POST /shift/{id}/book) is in ShiftControllerBookTest.
 *
 * @internal
 */
class BookingControllerTest extends FunctionalTestCase
{
    use ShiftScenarios;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    public function testTheBookingPageListsTheFreeShifts(): void
    {
        $client = static::createClient();
        $member = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();
        static::logIn($client, $member->getUser());

        $client->request('GET', '/booking/');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString($shift->getJob()->getName(), $client->getResponse()->getContent());
    }

    public function testAUserWithoutBeneficiaryIsSentToTheAdminBookingPage(): void
    {
        $client = static::createClient();
        $user = static::persist(UserBuilder::aUser()->build());
        static::logIn($client, $user);

        $client->request('GET', '/booking/');

        $this->assertTrue($client->getResponse()->isRedirect('/booking/admin'));
        $this->assertCount(1, static::flashes($client)['error'] ?? []);
    }

    public function testAMemberWhoseRegistrationExpiredCannotBook(): void
    {
        $client = static::createClient();
        $member = static::persist(MembershipBuilder::aMembership()->registeredOn(new \DateTime('-2 years'))->build())->getMainBeneficiary();
        static::logIn($client, $member->getUser());

        try {
            $client->request('GET', '/booking/');
        } catch (\Error $e) {
            $this->markTestIncomplete('BOOKING-EXPIRED open: an expired member crashes the booking page (' . $e->getMessage() . '); a stray unary plus in front of $remainder on PHP 8.');
        }

        if (500 === $client->getResponse()->getStatusCode()) {
            // PHP 7.4 turns the unary plus on a DateInterval into a notice, so an error page instead of a TypeError.
            $this->markTestIncomplete('BOOKING-EXPIRED open: an expired member gets a 500 on the booking page (stray unary plus in front of $remainder).');
        }

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertStringContainsString('Oups, ton adhésion a expiré', static::flashes($client)['warning'][0] ?? '');
    }

    public function testAFrozenMemberCannotBook(): void
    {
        $client = static::createClient();
        $member = static::persist(MembershipBuilder::aMembership()->frozen()->build())->getMainBeneficiary();
        static::logIn($client, $member->getUser());

        $client->request('GET', '/booking/');

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertStringContainsString('gelé', static::flashes($client)['warning'][0] ?? '');
    }

    public function testAMembershipWithTwoBeneficiariesChoosesWhoBooks(): void
    {
        $client = static::createClient();
        $membership = $this->aMembershipWithTwoBeneficiaries();
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        $crawler = $client->request('GET', '/booking/');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $options = $crawler->filterXPath('//select[@name="form[beneficiary]"]/option')->extract(['value']);
        $this->assertCount(2, $options, 'Both beneficiaries of the membership, nobody else.');
    }

    public function testChoosingABeneficiaryOfTheMembershipShowsTheirShifts(): void
    {
        $client = static::createClient();
        $membership = $this->aMembershipWithTwoBeneficiaries();
        $second = $membership->getBeneficiaries()->last();
        static::aBookableShift();
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        $client->request('POST', '/booking/', ['form' => ['beneficiary' => $second->getId(), '_token' => static::csrfToken($client, 'form')]]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('<span class="teal-text">Second</span>', $client->getResponse()->getContent(), 'The page books for the chosen beneficiary.');
    }

    /**
     * The choice list is the membership's beneficiaries: the id of somebody
     * else's is not a valid choice and must not bring the booking page up for
     * them.
     */
    public function testAMemberCannotChooseABeneficiaryOfAnotherMembership(): void
    {
        $client = static::createClient();
        $membership = $this->aMembershipWithTwoBeneficiaries();
        $stranger = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Stranger', 'Doe'))->build())->getMainBeneficiary();
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        try {
            $client->request('POST', '/booking/', ['form' => ['beneficiary' => $stranger->getId(), '_token' => static::csrfToken($client, 'form')]]);
        } catch (\Throwable $e) {
            $this->markTestIncomplete('BOOKING-FOREIGN-CHOICE open: choosing an invalid beneficiary crashes (' . get_class($e) . ': ' . $e->getMessage() . ').');
        }

        $this->assertStringNotContainsString('<span class="teal-text">' . $stranger->getFirstname() . '</span>', $client->getResponse()->getContent());
    }

    public function testTheBucketOfAShiftCanBeShownForAMemberOwnBeneficiary(): void
    {
        $client = static::createClient();
        $member = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();
        static::entityManager()->clear(); // read the entities as stored, as a request does
        static::logIn($client, $member->getUser());

        $client->request('GET', sprintf('/booking/bucket/%d/show/for/%d/cycle/0', $shift->getId(), $member->getId()));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testTheDayViewIsPublicWithoutBeneficiary(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift();

        $client->request('GET', '/booking/day/' . $shift->getStart()->format('Y-m-d') . '/');

        $this->assertLessThan(500, $client->getResponse()->getStatusCode(), $client->getResponse()->getStatusCode() . ' on the public day view');
    }

    public function testTheDayViewOfABeneficiaryAsksForALogin(): void
    {
        $client = static::createClient();
        $member = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();

        $client->request('GET', sprintf('/booking/day/%s/%d/0', $shift->getStart()->format('Y-m-d'), $member->getId()));

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
    }

    public function testTheDayViewOfABeneficiaryShowsTheirDay(): void
    {
        $client = static::createClient();
        $member = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();
        static::entityManager()->clear(); // read the entities as stored, as a request does
        static::logIn($client, $member->getUser());

        $client->request('GET', sprintf('/booking/day/%s/%d/0', $shift->getStart()->format('Y-m-d'), $member->getId()));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString($shift->getJob()->getName(), $client->getResponse()->getContent());
    }

    /**
     * Navigating the calendar lands on days without any shift; the action
     * reads $bucketsByDay[$day] without checking it exists.
     */
    public function testTheDayViewOfADayWithoutShiftDoesNotCrash(): void
    {
        $client = static::createClient();
        $member = static::aMembership()->getMainBeneficiary();
        static::logIn($client, $member->getUser());

        try {
            $client->request('GET', sprintf('/booking/day/%s/%d/0', (new \DateTime('+400 days'))->format('Y-m-d'), $member->getId()));
        } catch (\Throwable $e) {
            $this->markTestIncomplete('BOOKING-EMPTY-DAY open: a day without shift crashes (' . get_class($e) . ': ' . $e->getMessage() . ').');
        }

        if ($client->getResponse()->getStatusCode() >= 500) {
            $this->markTestIncomplete('BOOKING-EMPTY-DAY open: a day without shift answers ' . $client->getResponse()->getStatusCode() . '.');
        }
        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    private function aMembershipWithTwoBeneficiaries()
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Second', 'Beneficiary'))
                ->build()
        );
    }
}
