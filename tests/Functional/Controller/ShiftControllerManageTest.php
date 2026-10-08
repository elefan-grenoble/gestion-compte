<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\Shift;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * What a shift manager does to the shifts of the others: book for a member
 * (shift_book_admin), validate or invalidate a participation
 * (shift_validate_admin) and, for an admin, delete a shift (shift_delete).
 *
 * @internal
 */
class ShiftControllerManageTest extends FunctionalTestCase
{
    use ShiftScenarios;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    // --- book for a member ------------------------------------------------

    public function testAShiftManagerBooksAShiftForAMember(): void
    {
        $client = static::createClient();
        $member = $this->aMemberWithAnAddress();
        $shift = static::aBookableShift();
        $manager = $this->aMemberWithRoles('ROLE_SHIFT_MANAGER');
        static::logIn($client, $manager);

        $this->postBookAdmin($client, $shift, $member->getMainBeneficiary());

        $this->assertTrue($client->getResponse()->isRedirect('/booking/admin'));
        $this->assertCount(1, array_filter(static::flashes($client)['success'] ?? [], function ($m) { return 0 === strpos($m, 'Créneau réservé avec succès'); }));
        $shift = static::reloaded($shift);
        $this->assertSame($member->getMainBeneficiary()->getId(), $shift->getShifter()->getId());
        $this->assertSame($manager->getId(), $shift->getBooker()->getId(), 'The booker is the manager, not the member.');
        $this->assertNotNull($shift->getBookedTime());
        $this->assertEquals(new \DateTime('today'), static::reloaded($member)->getFirstShiftDate());
    }

    public function testAShiftManagerCannotBookAShiftThatIsTaken(): void
    {
        $client = static::createClient();
        $holder = static::aMembership()->getMainBeneficiary();
        $other = $this->aMemberWithAnAddress()->getMainBeneficiary();
        $shift = ShiftBuilder::aShift()->bookedBy($holder)->build();
        static::persist($shift->getJob(), $shift);
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postBookAdmin($client, $shift, $other);

        $this->assertSame(['Désolé, ce créneau est déjà réservé'], static::flashes($client)['error'] ?? []);
        $this->assertSame($holder->getId(), static::reloaded($shift)->getShifter()->getId());
    }

    public function testAShiftManagerCannotBookAMemberWithoutTheFormationTheShiftNeeds(): void
    {
        $client = static::createClient();
        $member = $this->aMemberWithAnAddress();
        $shift = static::aBookableShift();
        $formation = new \App\Entity\Formation();
        $formation->setName('formation-' . uniqid());
        static::persist($formation);
        $shift->setFormation($formation);
        static::persist($shift);
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postBookAdmin($client, $shift, $member->getMainBeneficiary());

        $this->assertSame(["Désolé, ce bénévole n'a pas la qualification necessaire (" . $formation->getName() . ')'], static::flashes($client)['error'] ?? []);
        $this->assertNull(static::reloaded($shift)->getShifter());
    }

    public function testAShiftManagerCannotBookTheirOwnShiftWhenTheConfigurationForbidsIt(): void
    {
        static::withEnv(['FORBID_OWN_SHIFT_BOOK_ADMIN' => 'true'], function () {
            $client = static::createClient();
            $manager = $this->aMemberWithRoles('ROLE_SHIFT_MANAGER');
            $shift = static::aBookableShift();
            static::logIn($client, $manager);

            $this->postBookAdmin($client, $shift, $manager->getBeneficiary());

            $this->assertSame(['Vous ne pouvez pas réserver votre propre créneau.'], static::flashes($client)['error'] ?? []);
            $this->assertNull(static::reloaded($shift)->getShifter());
        });
    }

    public function testAnOrdinaryMemberCannotBookThroughTheAdminRoute(): void
    {
        $client = static::createClient();
        $me = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift();
        static::logIn($client, $me->getUser());

        $this->postBookAdmin($client, $shift, $me);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertNull(static::reloaded($shift)->getShifter());
    }

    public function testBookingForAMemberThroughAjaxAnswersJson(): void
    {
        $client = static::createClient();
        $member = $this->aMemberWithAnAddress();
        $shift = static::aBookableShift();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postBookAdmin($client, $shift, $member->getMainBeneficiary(), ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertArrayHasKey('card', json_decode($client->getResponse()->getContent(), true));
        $this->assertNotNull(static::reloaded($shift)->getShifter());
    }

    // --- validate / invalidate --------------------------------------------

    public function testAShiftManagerValidatesAParticipation(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-1 day 09:00'));
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postValidate($client, $shift, 1);

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/booking/admin'));
        $this->assertSame(['La participation au créneau a bien été validée !'], static::flashes($client)['success'] ?? []);
        $this->assertTrue((bool) static::reloaded($shift)->getWasCarriedOut());
    }

    public function testAShiftManagerInvalidatesAParticipation(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-1 day 09:00'), true);
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postValidate($client, $shift, 0);

        $this->assertSame(['La participation au créneau a bien été invalidée !'], static::flashes($client)['success'] ?? []);
        $this->assertFalse((bool) static::reloaded($shift)->getWasCarriedOut());
    }

    public function testValidatingTwiceIsRefused(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-1 day 09:00'), true);
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postValidate($client, $shift, 1);

        $this->assertSame(['La participation au créneau a déjà été validée'], static::flashes($client)['error'] ?? []);
        $this->assertTrue((bool) static::reloaded($shift)->getWasCarriedOut());
    }

    public function testInvalidatingAnUnvalidatedShiftIsRefused(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-1 day 09:00'));
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postValidate($client, $shift, 0);

        $this->assertSame(['La participation au créneau a déjà été invalidée'], static::flashes($client)['error'] ?? []);
    }

    public function testAShiftMemberCannotValidateTheirOwnShift(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-1 day 09:00'));
        static::logIn($client, $shifter->getUser());

        $this->postValidate($client, $shift, 1);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertFalse((bool) static::reloaded($shift)->getWasCarriedOut());
    }

    public function testAShiftManagerCannotValidateTheirOwnShiftWhenTheConfigurationForbidsIt(): void
    {
        static::withEnv(['FORBID_OWN_SHIFT_VALIDATE_ADMIN' => 'true'], function () {
            $client = static::createClient();
            $manager = $this->aMemberWithRoles('ROLE_SHIFT_MANAGER');
            $shift = $this->aShiftBookedBy($manager->getBeneficiary(), new \DateTime('-1 day 09:00'));
            static::logIn($client, $manager);

            $this->postValidate($client, $shift, 1);

            $this->assertSame(['Vous ne pouvez pas valider votre propre créneau.'], static::flashes($client)['error'] ?? []);
            $this->assertFalse((bool) static::reloaded($shift)->getWasCarriedOut());
        });
    }

    public function testValidatingThroughAjaxAnswersJson(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('-1 day 09:00'));
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $this->postValidate($client, $shift, 1, ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('La participation au créneau a bien été validée !', json_decode($client->getResponse()->getContent(), true)['message']);
    }

    // --- delete -------------------------------------------------------------

    public function testAnAdminDeletesAShift(): void
    {
        $client = static::createClient();
        $shifter = static::aMembership()->getMainBeneficiary();
        $shift = $this->aShiftBookedBy($shifter, new \DateTime('+3 days 09:00'));
        $id = $shift->getId();
        static::logIn($client, $this->aMemberWithRoles('ROLE_ADMIN'));

        $name = 'shift_delete_forms_' . $id;
        $client->request('DELETE', '/shift/' . $id, [$name => ['_token' => static::csrfToken($client, $name)]]);

        $this->assertTrue($client->getResponse()->isRedirect('/booking/admin'));
        $this->assertSame(['Le créneau a bien été supprimé !'], static::flashes($client)['success'] ?? []);
        $this->assertNull(static::entityManager()->getRepository(Shift::class)->find($id));
    }

    public function testAShiftManagerCannotDeleteAShift(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift();
        static::logIn($client, $this->aMemberWithRoles('ROLE_SHIFT_MANAGER'));

        $name = 'shift_delete_forms_' . $shift->getId();
        $client->request('DELETE', '/shift/' . $shift->getId(), [$name => ['_token' => static::csrfToken($client, $name)]]);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertNotNull(static::reloaded($shift));
    }

    public function testDeletingWithoutValidTokenKeepsTheShift(): void
    {
        $client = static::createClient();
        $shift = static::aBookableShift();
        static::logIn($client, $this->aMemberWithRoles('ROLE_ADMIN'));

        $client->request('DELETE', '/shift/' . $shift->getId(), ['shift_delete_forms_' . $shift->getId() => ['_token' => 'forged']]);

        $this->assertNotEmpty(static::flashes($client)['error'] ?? []);
        $this->assertNotNull(static::reloaded($shift));
    }

    // --- helpers ----------------------------------------------------------

    private function aShiftBookedBy(Beneficiary $shifter, \DateTime $start, bool $carriedOut = false): Shift
    {
        $shift = ShiftBuilder::aShift()->startingAt($start)->bookedBy($shifter)->carriedOut($carriedOut)->build();
        static::persist($shift->getJob(), $shift);
        static::entityManager()->clear();

        return $shift;
    }

    private function aMemberWithAnAddress(): Membership
    {
        return static::persist(
            MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withAddress())->build()
        );
    }

    private function aMemberWithRoles(string ...$roles): User
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withAddress()->withUser(UserBuilder::aUser()->withRoles(...$roles)))
                ->build()
        )->getMainBeneficiary()->getUser();
    }

    private function postBookAdmin($client, Shift $shift, Beneficiary $beneficiary, array $server = []): void
    {
        $name = 'shift_book_forms_' . $shift->getId();
        // The member number comes from the membership: read the beneficiary as stored.
        $beneficiary = static::reloaded($beneficiary);
        $client->request(
            'POST',
            '/shift/' . $shift->getId() . '/book_admin',
            [$name => ['shifter' => sprintf('#%d %s %s', $beneficiary->getMemberNumber(), $beneficiary->getFirstname(), $beneficiary->getLastname()), 'fixe' => 0, '_token' => static::csrfToken($client, $name)]],
            [],
            $server
        );
    }

    private function postValidate($client, Shift $shift, int $validate, array $server = []): void
    {
        $name = 'shift_validate_invalidate_forms_' . $shift->getId();
        $client->request(
            'POST',
            '/shift/' . $shift->getId() . '/validate_admin',
            [$name => ['validate' => $validate, '_token' => static::csrfToken($client, $name)]],
            [],
            $server + ['HTTP_REFERER' => 'http://localhost/booking/admin']
        );
    }
}
