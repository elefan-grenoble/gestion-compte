<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\TimeLog;
use App\Entity\ShiftFreeLog;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\ShiftScenarios;
use App\Entity\Beneficiary;
use App\Entity\User;

/**
 * Freeing a shift: POST /shift/{id}/free (the shifter) and
 * POST /shift/{id}/free_admin (a shift manager).
 *
 * @internal
 */
class ShiftControllerFreeTest extends FunctionalTestCase
{
    use ShiftScenarios;

    /**
     * Saving mode, without a minimum delay to free a shift (.env.test sets
     * the delay to the string "null", which is not empty).
     */
    private const SAVING_MODE = [
        'USE_TIME_LOG_SAVING' => 'true',
        'USE_CARD_READER_TO_VALIDATE_SHIFTS' => 'true',
        'TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE_DAYS' => '',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    // --- the shifter frees their own shift -------------------------------

    public function testTheShifterFreesTheirFutureShift(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $shifter->getUser());

        $this->postFree($client, $shift, 'I am ill');

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertSame(['Le créneau a été annulé !'], static::flashes($client)['success'] ?? []);

        $shift = static::reloaded($shift);
        $this->assertNull($shift->getShifter());
        $this->assertNull($shift->getBooker());
        $this->assertNull($shift->getBookedTime());
        $this->assertFalse($shift->isFixe());
    }

    public function testFreeingKeepsATraceOfWhoFreedWhatAndWhy(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $shifter->getUser());

        $this->postFree($client, $shift, 'I am ill');

        $logs = static::entityManager()->getRepository(ShiftFreeLog::class)->findBy(['shift' => $shift->getId()]);
        $this->assertCount(1, $logs);
        $this->assertSame($shifter->getId(), $logs[0]->getBeneficiary()->getId());
        $this->assertSame('I am ill', $logs[0]->getReason());
    }

    public function testFreeingTakesTheShiftOutOfTheMemberTimeCounter(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        $shifter = $membership->getMainBeneficiary();
        $shift = static::aBookableShift(new \DateTime('+3 days 09:00'));
        static::logIn($client, $shifter->getUser());
        static::postJson($client, '/shift/' . $shift->getId() . '/book', ['beneficiaryId' => $shifter->getId(), 'typeService' => 0]);
        $this->assertSame(180, static::reloaded($membership)->getShiftTimeCount());

        $this->postFree($client, $shift);

        $this->assertSame(0, static::reloaded($membership)->getShiftTimeCount());
    }

    public function testAnotherMemberCannotFreeTheShift(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $other = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $other->getUser());

        $this->postFree($client, $shift);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertSame($shifter->getId(), static::reloaded($shift)->getShifter()->getId());
    }

    public function testAFormWithoutValidTokenDoesNotFreeTheShift(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $shifter->getUser());

        $client->request('POST', '/shift/' . $shift->getId() . '/free', ['form' => ['reason' => 'x', '_token' => 'forged']]);

        $this->assertSame($shifter->getId(), static::reloaded($shift)->getShifter()->getId());
        $this->assertArrayNotHasKey('success', static::flashes($client));
    }

    public function testFreeingIsAPostOnly(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $shifter->getUser());

        $client->request('GET', '/shift/' . $shift->getId() . '/free');

        $this->assertSame(405, $client->getResponse()->getStatusCode());
        $this->assertNotNull(static::reloaded($shift)->getShifter());
    }

    public function testAShiftThatHasStartedCannotBeFreedByItsShifter(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-30 minutes'));
        static::logIn($client, $shifter->getUser());

        $this->postFree($client, $shift);

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertSame($shifter->getId(), static::reloaded($shift)->getShifter()->getId());
        $this->assertArrayHasKey('error', static::flashes($client));
    }

    /**
     * The refusal tells why (SHIFT-FREE-MESSAGE: the flash used to read "1").
     */
    public function testTheRefusalToFreeAStartedShiftSaysWhy(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-30 minutes'));
        static::logIn($client, $shifter->getUser());

        $this->postFree($client, $shift);

        $this->assertSame(['Impossible de libérer un créneau dans le passé.'], static::flashes($client)['error'] ?? []);
    }

    // --- a shift manager frees a shift -----------------------------------

    public function testAShiftManagerFreesAShiftAndIsSentBack(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $this->aShiftManager());

        $this->postFreeAdmin($client, $shift, 'planning change');

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/booking/admin'));
        $this->assertSame(['Le créneau a bien été libéré !'], static::flashes($client)['success'] ?? []);
        $this->assertNull(static::reloaded($shift)->getShifter());
        $log = static::entityManager()->getRepository(ShiftFreeLog::class)->findOneBy(['shift' => $shift->getId()]);
        $this->assertSame('planning change', $log->getReason());
        $this->assertSame($shifter->getId(), $log->getBeneficiary()->getId());
    }

    public function testAShiftManagerCanFreeAShiftThatHasAlreadyStarted(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-1 hour'));
        static::logIn($client, $this->aShiftManager());

        $this->postFreeAdmin($client, $shift);

        $this->assertNull(static::reloaded($shift)->getShifter());
    }

    public function testFreeingAValidatedShiftInvalidatesItFirst(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-1 day 09:00'), true);
        static::logIn($client, $this->aShiftManager());

        $this->postFreeAdmin($client, $shift);

        $shift = static::reloaded($shift);
        $this->assertNull($shift->getShifter());
        $this->assertFalse((bool) $shift->getWasCarriedOut(), 'A free shift cannot stay validated.');
    }

    public function testAnOrdinaryMemberCannotFreeThroughTheAdminRoute(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $shifter->getUser());

        $this->postFreeAdmin($client, $shift);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertNotNull(static::reloaded($shift)->getShifter());
    }

    public function testAShiftManagerCannotFreeTheirOwnShiftWhenTheConfigurationForbidsIt(): void
    {
        static::withEnv(['FORBID_OWN_SHIFT_FREE_ADMIN' => 'true'], function () {
            $client = static::createClient();
            $managerUser = $this->aShiftManager();
            $shift = $this->aShiftBookedBy($managerUser->getBeneficiary(), new \DateTime('+3 days 09:00'));
            static::logIn($client, $managerUser);

            $this->postFreeAdmin($client, $shift);

            $this->assertSame(['Vous ne pouvez pas annuler votre propre créneau.'], static::flashes($client)['error'] ?? []);
            $this->assertNotNull(static::reloaded($shift)->getShifter());
        });
    }

    public function testAnAdminCanFreeTheirOwnShiftEvenWhenTheConfigurationForbidsIt(): void
    {
        static::withEnv(['FORBID_OWN_SHIFT_FREE_ADMIN' => 'true'], function () {
            $client = static::createClient();
            $adminUser = $this->aMemberWithRoles('ROLE_ADMIN');
            $shift = $this->aShiftBookedBy($adminUser->getBeneficiary(), new \DateTime('+3 days 09:00'));
            static::logIn($client, $adminUser);

            $this->postFreeAdmin($client, $shift);

            $this->assertNull(static::reloaded($shift)->getShifter());
        });
    }

    public function testFreeingThroughAjaxAnswersJson(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $this->aShiftManager());

        $this->postFreeAdmin($client, $shift, '', ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Le créneau a bien été libéré !', $body['message']);
        $this->assertArrayHasKey('card', $body);
        $this->assertArrayHasKey('modal', $body);
        $this->assertNull(static::reloaded($shift)->getShifter());
    }

    public function testAnAjaxFreeWithABadTokenAnswers400(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $this->aShiftManager());

        $client->request('POST', '/shift/' . $shift->getId() . '/free_admin', ['shift_free_forms_' . $shift->getId() => ['_token' => 'forged']], [], ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertNotNull(static::reloaded($shift)->getShifter());
    }

    /**
     * In saving mode, freeing a shift whose time is not taken from the
     * member's saving counter only says that the shift is freed, whatever
     * the saving of the other members.
     */
    public function testFreeingInSavingModeWithoutSavingOnlySaysTheShiftIsFreed(): void
    {
        static::withEnv(self::SAVING_MODE, function () {
            $client = static::createClient();
            $shifter = static::aMembership()->getMainBeneficiary();
            $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
            $this->aSavingTimeLog(static::aMembership(), 600);
            static::logIn($client, $this->aShiftManager());

            $this->postFreeAdmin($client, $shift);

            $this->assertSame(['Le créneau a bien été libéré !'], static::flashes($client)['success'] ?? []);
            $this->assertNull(static::reloaded($shift)->getShifter());
        });
    }

    /**
     * In saving mode, the shift's time comes out of the member's saving
     * counter and the message says so (SHIFT-FREE-SAVING-MESSAGE: the
     * message was appended with `+=`, a TypeError on PHP 8).
     */
    public function testFreeingInSavingModeTellsTheTimeComesFromTheSaving(): void
    {
        static::withEnv(self::SAVING_MODE, function () {
            $client = static::createClient();
            $membership = static::aMembership();
            $this->aSavingTimeLog($membership, 600);
            $shift = $this->aShiftBookedBy($membership->getMainBeneficiary(), new \DateTime('+3 days 09:00'));
            static::logIn($client, $this->aShiftManager());

            $this->postFreeAdmin($client, $shift);

            $this->assertSame(['Le créneau a bien été libéré ! Grâce au compteur épargne, le créneau a été comptabilisé (en échange, le compteur épargne a été décrémenté de la durée du créneau).'], static::flashes($client)['success'] ?? []);
            $this->assertNull(static::reloaded($shift)->getShifter());
        });
    }

    /**
     * Without a Referer header (privacy extension, cross-origin post) the
     * manager is sent to the admin booking page (SHIFT-NO-REFERER: the
     * action used to build `new RedirectResponse(null)` and answer 500).
     */
    public function testFreeingWithoutARefererStillRedirects(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        static::logIn($client, $this->aShiftManager());
        $token = static::csrfToken($client, 'shift_free_forms_' . $shift->getId());

        $client->request('POST', '/shift/' . $shift->getId() . '/free_admin', ['shift_free_forms_' . $shift->getId() => ['reason' => '', '_token' => $token]]);

        $this->assertTrue($client->getResponse()->isRedirect('/booking/admin'), (string) $client->getResponse()->headers->get('Location'));
        $this->assertNull(static::reloaded($shift)->getShifter());
    }

    /**
     * Freeing a shift nobody holds is refused with the message "not
     * currently booked" (SHIFT-FREE-FREE: it used to pass null to a typed
     * argument of ShiftService::canFreeShift()).
     */
    public function testFreeingAFreeShiftIsRefusedWithAMessage(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift(new \DateTime('+3 days 09:00'));
        static::logIn($client, $this->aShiftManager());

        $this->postFreeAdmin($client, $shift);

        $this->assertSame(["Impossible de libérer le créneau car il n'est actuellement pas réservé."], static::flashes($client)['error'] ?? []);
    }

    // --- helpers ----------------------------------------------------------

    private function aShiftBookedBy(Beneficiary $shifter, \DateTime $start, bool $carriedOut = false): Shift
    {
        $shift = ShiftBuilder::aShift()->startingAt($start)->bookedBy($shifter)->carriedOut($carriedOut)->build();
        static::persist($shift->getJob(), $shift);
        // Work on what the database holds, as a request does (a shift built in memory has no collections yet).
        static::entityManager()->clear();

        return $shift;
    }

    private function aSavingTimeLog(Membership $membership, int $minutes): TimeLog
    {
        $log = new TimeLog();
        $log->setMembership(static::entityManager()->find(Membership::class, $membership->getId()));
        $log->setType(TimeLog::TYPE_SAVING);
        $log->setTime($minutes);

        return static::persist($log);
    }

    private function aShiftManager(): User
    {
        return $this->aMemberWithRoles('ROLE_SHIFT_MANAGER');
    }

    private function aMemberWithRoles(string ...$roles): User
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser(UserBuilder::aUser()->withRoles(...$roles)))
                ->build()
        )->getMainBeneficiary()->getUser();
    }

    private function postFree($client, Shift $shift, string $reason = ''): void
    {
        $client->request('POST', '/shift/' . $shift->getId() . '/free', ['form' => ['reason' => $reason, '_token' => static::csrfToken($client, 'form')]]);
    }

    private function postFreeAdmin($client, Shift $shift, string $reason = '', array $server = []): void
    {
        $name = 'shift_free_forms_' . $shift->getId();
        $client->request(
            'POST',
            '/shift/' . $shift->getId() . '/free_admin',
            [$name => ['reason' => $reason, '_token' => static::csrfToken($client, $name)]],
            [],
            $server + ['HTTP_REFERER' => 'http://localhost/booking/admin']
        );
    }
}
