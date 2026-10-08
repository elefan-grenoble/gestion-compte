<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Membership;
use App\Entity\Registration;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * Recording a re-registration of a membership
 * (POST /member/{member_number}/newRegistration).
 *
 * @internal
 */
class MembershipControllerRegistrationTest extends FunctionalTestCase
{
    use ShiftScenarios;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    public function testAnAdminRecordsTheReRegistrationOfAnExpiredMembership(): void
    {
        $client = static::createClient();
        $member = static::persist(MembershipBuilder::aMembership()->registeredOn(new \DateTime('-2 years'))->build());
        $admin = $this->anAdmin();
        static::logIn($client, $admin);
        $date = new \DateTime('today');

        $this->postRegistration($client, $member, $date, '25', $admin);

        $this->assertSame(['Enregistrement effectuée'], static::flashes($client)['success'] ?? [], json_encode(static::flashes($client)));
        $registrations = static::reloaded($member)->getRegistrations();
        $this->assertCount(2, $registrations);
        $last = static::reloaded($member)->getLastRegistration();
        $this->assertEquals($date, $last->getDate());
        $this->assertEquals(25, $last->getAmount());
        $this->assertSame(Registration::TYPE_CASH, $last->getMode());
        $this->assertSame($admin->getId(), $last->getRegistrar()->getId());
    }

    public function testAMembershipStillCoveredIsNotRegisteredAgainForTheSameDate(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        $admin = $this->anAdmin();
        static::logIn($client, $admin);

        $this->postRegistration($client, $member, new \DateTime('today'), '25', $admin);

        $this->assertSame(["l'adhésion précédente est encore valable à cette date !"], static::flashes($client)['warning'] ?? []);
        $this->assertCount(1, static::reloaded($member)->getRegistrations());
    }

    public function testAMemberCannotRecordTheirOwnReRegistration(): void
    {
        $client = static::createClient();
        $admin = $this->anAdmin();
        $own = $admin->getBeneficiary()->getMembership();
        static::logIn($client, $admin);

        $this->postRegistration($client, $own, new \DateTime('+2 years'), '25', $admin);

        $this->assertSame(['Tu ne peux pas enregistrer ta propre ré-adhésion, demande à un autre adhérent :)'], static::flashes($client)['error'] ?? []);
        $this->assertCount(1, static::reloaded($own)->getRegistrations());
    }

    public function testAnOrdinaryMemberCannotRecordARegistrationForAnotherMembership(): void
    {
        $client = static::createClient();
        $me = static::aMembership();
        $other = static::persist(MembershipBuilder::aMembership()->registeredOn(new \DateTime('-2 years'))->build());
        static::logIn($client, $me->getMainBeneficiary()->getUser());

        $this->postRegistration($client, $other, new \DateTime('today'), '25', $me->getMainBeneficiary()->getUser());

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertCount(1, static::reloaded($other)->getRegistrations());
    }

    private function anAdmin(): User
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withAddress()->withUser(UserBuilder::aUser()->withRoles('ROLE_ADMIN')))
                ->build()
        )->getMainBeneficiary()->getUser();
    }

    private function postRegistration($client, Membership $member, \DateTime $date, string $amount, User $registrar): void
    {
        $client->request('POST', sprintf('/member/%d/newRegistration', $member->getMemberNumber()), ['registration' => [
            'date' => $date->format('Y-m-d'),
            'amount' => $amount,
            'registrar' => $registrar->getId(),
            'mode' => Registration::TYPE_CASH,
            'is_new' => '1',
            '_token' => static::csrfToken($client, 'registration'),
        ]]);
    }
}
