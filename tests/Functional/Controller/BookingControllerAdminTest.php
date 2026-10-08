<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Job;
use App\Entity\Shift;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * The back-office side of the booking: the planning page, the buckets (all
 * the shifts sharing job, start and end) and the creation of shifts.
 *
 * @internal
 */
class BookingControllerAdminTest extends FunctionalTestCase
{
    use ShiftScenarios;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    // --- planning page ----------------------------------------------------

    public function testThePlanningListsTheBucketsFromThisWeek(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift(new \DateTime('+2 days 09:00'));
        static::entityManager()->clear();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $client->request('GET', '/booking/admin');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString($shift->getJob()->getName(), $client->getResponse()->getContent());
    }

    public function testThePlanningCanBeFilteredByJob(): void
    {
        $client = static::createClient();
        $wanted = static::aBookableShift(new \DateTime('+2 days 09:00'));
        static::aBookableShift(new \DateTime('+2 days 14:00'));
        static::entityManager()->clear();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $client->request('POST', '/booking/admin', ['form' => [
            'type' => 'date',
            'from' => (new \DateTime('today'))->format('Y-m-d'),
            'to' => '',
            'year' => (new \DateTime())->format('Y'),
            'week' => (new \DateTime())->format('W'),
            'job' => $wanted->getJob()->getId(),
            'filling' => '',
            'filter' => '',
            '_token' => static::csrfToken($client, 'form'),
        ]]);

        $content = $client->getResponse()->getContent();
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame(1, substr_count($content, 'data-source="/booking/admin/bucket/'), 'Only the bucket of the chosen job is listed.');
    }

    public function testTheBucketModalShowsTheFormsToManageEachShift(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift(new \DateTime('+2 days 09:00'));
        static::entityManager()->clear();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $client->request('GET', '/booking/admin/bucket/' . $shift->getId() . '/show');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('name="shift_book_forms_' . $shift->getId() . '"', $content, 'A free shift can be booked for a member.');
        $this->assertStringContainsString('name="bucket_delete_form"', $content);
        $this->assertStringContainsString('name="bucket_lock_unlock_form"', $content);
    }

    // --- edit a bucket ----------------------------------------------------

    public function testEditingABucketMovesAllItsShifts(): void
    {
        $client = static::createClient();
        $start = new \DateTime('+3 days 09:00');
        $shift = static::aBookableShift($start);
        $sibling = ShiftBuilder::aShift()->startingAt($start)->forJob($shift->getJob())->build();
        $elsewhere = ShiftBuilder::aShift()->startingAt(new \DateTime('+3 days 14:00'))->forJob($shift->getJob())->build();
        static::persist($sibling, $elsewhere);
        static::entityManager()->clear();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $newStart = new \DateTime('+4 days 10:00');
        $newEnd = (clone $newStart)->modify('+2 hours');
        $client->request('POST', '/booking/bucket/' . $shift->getId() . '/edit', ['App_shift' => [
            'start' => ['date' => $newStart->format('Y-m-d'), 'time' => $newStart->format('H:i')],
            'end' => ['date' => $newEnd->format('Y-m-d'), 'time' => $newEnd->format('H:i')],
            'job' => $shift->getJob()->getId(),
            '_token' => static::csrfToken($client, 'App_shift'),
        ]]);

        $this->assertTrue($client->getResponse()->isRedirect('/booking/admin'), 'Answered ' . $client->getResponse()->getStatusCode());
        $this->assertSame(['Le créneau a bien été édité !'], static::flashes($client)['success'] ?? []);
        foreach ([$shift, $sibling] as $moved) {
            $moved = static::reloaded($moved);
            $this->assertEquals($newStart, $moved->getStart());
            $this->assertEquals($newEnd, $moved->getEnd());
        }
        $this->assertEquals(new \DateTime('+3 days 14:00'), static::reloaded($elsewhere)->getStart(), 'Another bucket is left alone.');
    }

    // --- lock / unlock ----------------------------------------------------

    public function testLockingABucketLocksAllItsShifts(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift(new \DateTime('+3 days 09:00'));
        $sibling = ShiftBuilder::aShift()->startingAt($shift->getStart())->forJob($shift->getJob())->build();
        static::persist($sibling);
        static::entityManager()->clear();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postLock($client, $shift, 1);

        $this->assertTrue($client->getResponse()->isRedirect('/booking/admin'));
        $this->assertSame(['Le créneau a été verrouillé'], static::flashes($client)['success'] ?? []);
        $this->assertTrue(static::reloaded($shift)->isLocked());
        $this->assertTrue(static::reloaded($sibling)->isLocked());
    }

    public function testLockingALockedBucketIsRefused(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift(new \DateTime('+3 days 09:00'));
        $this->lockBucket($shift);
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postLock($client, $shift, 1);

        $this->assertSame(['Le créneau a déjà été verrouillé'], static::flashes($client)['error'] ?? []);
    }

    public function testUnlockingABucketUnlocksAllItsShifts(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift(new \DateTime('+3 days 09:00'));
        $this->lockBucket($shift);
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postLock($client, $shift, 0);

        $this->assertSame(['Le créneau a été déverrouillé'], static::flashes($client)['success'] ?? []);
        $shifts = static::entityManager()->getRepository(Shift::class)->findBy(['job' => $shift->getJob()->getId(), 'start' => $shift->getStart()]);
        $this->assertCount(2, $shifts);
        foreach ($shifts as $each) {
            $this->assertFalse($each->isLocked());
        }
    }

    public function testAMemberCannotLockABucket(): void
    {
        $client = static::createClient();
        $member = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift(new \DateTime('+3 days 09:00'));
        static::entityManager()->clear();
        static::logIn($client, $member->getUser());

        $this->postLock($client, $shift, 1);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertFalse(static::reloaded($shift)->isLocked());
    }

    // --- delete a bucket --------------------------------------------------

    public function testAnAdminDeletesAWholeBucket(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift(new \DateTime('+3 days 09:00'));
        $sibling = ShiftBuilder::aShift()->startingAt($shift->getStart())->forJob($shift->getJob())->build();
        $elsewhere = ShiftBuilder::aShift()->startingAt(new \DateTime('+3 days 14:00'))->forJob($shift->getJob())->build();
        static::persist($sibling, $elsewhere);
        static::entityManager()->clear();
        static::logIn($client, $this->aMemberWithRoles('ROLE_ADMIN'));

        $client->request('DELETE', '/booking/bucket/' . $shift->getId(), ['bucket_delete_form' => ['_token' => static::csrfToken($client, 'bucket_delete_form')]]);

        $this->assertTrue($client->getResponse()->isRedirect('/booking/admin'));
        $this->assertSame(['3 créneaux ont été supprimés !'], static::flashes($client)['success'] ?? [], 'The bucket holds the free shift, its sibling and the one already booked.');
        $repository = static::entityManager()->getRepository(Shift::class);
        $this->assertNull($repository->find($shift->getId()));
        $this->assertNull($repository->find($sibling->getId()));
        $this->assertNotNull($repository->find($elsewhere->getId()), 'Another bucket is left alone.');
    }

    public function testAShiftManagerCannotDeleteABucket(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift(new \DateTime('+3 days 09:00'));
        static::entityManager()->clear();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $client->request('DELETE', '/booking/bucket/' . $shift->getId(), ['bucket_delete_form' => ['_token' => static::csrfToken($client, 'bucket_delete_form')]]);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertNotNull(static::reloaded($shift));
    }

    public function testDeletingABucketWithoutValidTokenKeepsIt(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift(new \DateTime('+3 days 09:00'));
        static::entityManager()->clear();
        static::logIn($client, $this->aMemberWithRoles('ROLE_ADMIN'));

        $client->request('DELETE', '/booking/bucket/' . $shift->getId(), ['bucket_delete_form' => ['_token' => 'forged']]);

        $this->assertNotEmpty(static::flashes($client)['error'] ?? []);
        $this->assertNotNull(static::reloaded($shift));
    }

    // --- create shifts ----------------------------------------------------

    public function testAShiftManagerCreatesSeveralShiftsAtOnce(): void
    {
        $client = static::createClient();
        $job = $this->aJob();
        $manager = $this->aMemberWithRoles('ROLE_SHIFT_MANAGER');
        static::entityManager()->clear();
        static::logIn($client, $manager);
        $start = new \DateTime('+5 days 09:00');

        $this->postNewShift($client, $job, $start, (clone $start)->modify('+3 hours'), 3);

        $this->assertTrue($client->getResponse()->isRedirect('/booking/admin'), 'Answered ' . $client->getResponse()->getStatusCode());
        $this->assertSame(['Le créneau a bien été créé !'], static::flashes($client)['success'] ?? []);
        $created = static::entityManager()->getRepository(Shift::class)->findBy(['job' => $job->getId()]);
        $this->assertCount(3, $created);
        foreach ($created as $shift) {
            $this->assertEquals($start, $shift->getStart());
            $this->assertNull($shift->getShifter());
            $this->assertSame($manager->getId(), $shift->getCreatedBy()->getId());
        }
    }

    public function testAShiftThatEndsBeforeItStartsIsNotCreated(): void
    {
        $client = static::createClient();
        $job = $this->aJob();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));
        $start = new \DateTime('+5 days 09:00');

        $this->postNewShift($client, $job, $start, (clone $start)->modify('-1 hour'), 1);

        $this->assertSame(200, $client->getResponse()->getStatusCode(), 'The form is shown again.');
        $this->assertSame(0, static::entityManager()->getRepository(Shift::class)->count(['job' => $job->getId()]));
    }

    public function testCreatingShiftsThroughAjaxAnswers201(): void
    {
        $client = static::createClient();
        $job = $this->aJob();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));
        $start = new \DateTime('+5 days 09:00');

        $this->postNewShift($client, $job, $start, (clone $start)->modify('+3 hours'), 1, ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        $this->assertSame(201, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Le créneau a bien été créé !', $body['message']);
        $this->assertArrayHasKey('card', $body);
        $this->assertSame(1, static::entityManager()->getRepository(Shift::class)->count(['job' => $job->getId()]));
    }

    public function testAMemberCannotCreateShifts(): void
    {
        $client = static::createClient();
        $job = $this->aJob();
        $member = static::aMembership()->getMainBeneficiary();
        static::logIn($client, $member->getUser());
        $start = new \DateTime('+5 days 09:00');

        $this->postNewShift($client, $job, $start, (clone $start)->modify('+3 hours'), 1);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertSame(0, static::entityManager()->getRepository(Shift::class)->count(['job' => $job->getId()]));
    }

    // --- helpers ----------------------------------------------------------

    private function aJob(): Job
    {
        $job = new Job();
        $job->setName('new-shifts-job-' . uniqid());
        $job->setColor('#cccccc');
        $job->setMinShifterAlert(2);
        $job->setEnabled(true);

        return static::persist($job);
    }

    private function aMemberWithRoles(string ...$roles): User
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser(UserBuilder::aUser()->withRoles(...$roles)))
                ->build()
        )->getMainBeneficiary()->getUser();
    }

    /**
     * Locks every shift of the bucket, as the lock button does.
     */
    private function lockBucket(Shift $shift): void
    {
        $shifts = static::entityManager()->getRepository(Shift::class)->findBy(['job' => $shift->getJob()->getId(), 'start' => $shift->getStart(), 'end' => $shift->getEnd()]);
        foreach ($shifts as $each) {
            $each->setLocked(true);
        }
        static::persist(...$shifts);
        static::entityManager()->clear();
    }

    private function postLock($client, Shift $shift, int $lock): void
    {
        $client->request('POST', '/booking/bucket/' . $shift->getId() . '/lock', ['bucket_lock_unlock_form' => [
            'lock' => $lock,
            '_token' => static::csrfToken($client, 'bucket_lock_unlock_form'),
        ]]);
    }

    private function postNewShift($client, Job $job, \DateTime $start, \DateTime $end, int $number, array $server = []): void
    {
        $client->request('POST', '/shift/new', ['bucket_shift_add_form' => [
            'start' => ['date' => $start->format('Y-m-d'), 'time' => $start->format('H:i')],
            'end' => ['date' => $end->format('Y-m-d'), 'time' => $end->format('H:i')],
            'job' => $job->getId(),
            'formation' => '',
            'number' => $number,
            '_token' => static::csrfToken($client, 'bucket_shift_add_form'),
        ]], [], $server);
    }
}
