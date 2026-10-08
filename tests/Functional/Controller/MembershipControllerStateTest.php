<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Membership;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\Security\ChecksRoleAccess;
use App\Tests\Support\Security\KnownOpenVulnerability;
use App\Tests\Support\Security\RoleMatrix;
use App\Tests\Support\ShiftScenarios;

/**
 * The state changes of a membership: freeze, freeze change, withdrawal,
 * flying status and deletion, with who may do them.
 *
 * @internal
 */
class MembershipControllerStateTest extends FunctionalTestCase
{
    use ChecksRoleAccess;
    use KnownOpenVulnerability;
    use ShiftScenarios;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    /**
     * @return array<string, array{string, string, int|string}>
     */
    public function roleCases(): array
    {
        return RoleMatrix::cases([
            'member_freeze' => 'ROLE_USER_MANAGER',
            'member_unfreeze' => 'ROLE_USER_MANAGER',
            'member_freeze_change' => 'ROLE_USER_MANAGER',
            'member_flying' => 'ROLE_USER_MANAGER',
            'member_withdrawn' => 'ROLE_USER_MANAGER',
            'member_new_registration' => 'ROLE_USER_MANAGER',
            'member_delete' => 'ROLE_SUPER_ADMIN',
        ]);
    }

    /**
     * Acting on somebody else's membership.
     *
     * @dataProvider roleCases
     *
     * @param int|string $expected
     */
    public function testRoleAccessToAnotherMembership(string $route, string $role, $expected): void
    {
        $target = static::aMembership();
        $parameters = 'member_new_registration' === $route
            ? ['member_number' => $target->getMemberNumber()]
            : ['id' => $target->getId()];

        $this->assertRouteAccessForRole($route, $role, $expected, $parameters);
    }

    // --- freeze / unfreeze -------------------------------------------------

    public function testAUserManagerFreezesAMembership(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/freeze', $member->getId()));

        $this->assertTrue($client->getResponse()->isRedirect(sprintf('/member/%d/show', $member->getMemberNumber())));
        $this->assertSame(['Compte gelé !'], static::flashes($client)['success'] ?? []);
        $reloaded = static::reloaded($member);
        $this->assertTrue($reloaded->getFrozen());
        $this->assertFalse($reloaded->getFrozenChange(), 'A pending change request is dropped.');
    }

    public function testAUserManagerUnfreezesAMembership(): void
    {
        $client = static::createClient();
        $member = static::persist(MembershipBuilder::aMembership()->frozen()->build());
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/unfreeze', $member->getId()));

        $this->assertSame(['Compte dégelé !'], static::flashes($client)['success'] ?? []);
        $this->assertFalse(static::reloaded($member)->getFrozen());
    }

    public function testFreezingWithoutValidTokenChangesNothing(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $client->request('POST', sprintf('/member/%d/freeze', $member->getId()), ['form' => ['_token' => 'forged']]);

        $this->assertFalse(static::reloaded($member)->getFrozen());
        $this->assertArrayNotHasKey('success', static::flashes($client));
    }

    /**
     * SPEC.2: freeze and unfreeze are for managers; a member asks for a
     * change at the end of the cycle (freeze_change). The routes only check
     * the "freeze" voter, which also lets a member edit their own membership,
     * so a member freezes or unfreezes themselves at once.
     */
    public function testAMemberCannotFreezeTheirOwnAccountAtOnce(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        $this->postForm($client, sprintf('/member/%d/freeze', $membership->getId()));

        $this->assertSecureOrKnownOpen('MEMBER-FREEZE-SELF', 'a member freezes their own account immediately instead of asking for the end of the cycle', function () use ($membership) {
            $this->assertFalse(static::reloaded($membership)->getFrozen(), 'The member froze their own account.');
        });
    }

    public function testAMemberCannotUnfreezeTheirOwnAccountAtOnce(): void
    {
        $client = static::createClient();
        $membership = static::persist(MembershipBuilder::aMembership()->frozen()->build());
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        $this->postForm($client, sprintf('/member/%d/unfreeze', $membership->getId()));

        $this->assertSecureOrKnownOpen('MEMBER-FREEZE-SELF', 'a member unfreezes their own account immediately instead of asking for the end of the cycle', function () use ($membership) {
            $this->assertTrue(static::reloaded($membership)->getFrozen(), 'The member unfroze their own account.');
        });
    }

    // --- freeze change (the member asks for the end of the cycle) ----------

    public function testAMemberAsksToFreezeAtTheEndOfTheCycle(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        $this->postForm($client, sprintf('/member/%d/freeze_change', $membership->getId()));

        $this->assertTrue($client->getResponse()->isRedirect('/profile/'));
        $this->assertSame(['Le compte sera gelé à la fin du cycle !'], static::flashes($client)['success'] ?? []);
        $reloaded = static::reloaded($membership);
        $this->assertTrue($reloaded->getFrozenChange());
        $this->assertFalse($reloaded->getFrozen(), 'Nothing is frozen before the end of the cycle.');
    }

    public function testAskingAgainCancelsTheRequest(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        static::logIn($client, $membership->getMainBeneficiary()->getUser());
        $this->postForm($client, sprintf('/member/%d/freeze_change', $membership->getId()));

        $this->postForm($client, sprintf('/member/%d/freeze_change', $membership->getId()));

        // The first request's flash was never displayed, so it is still in the session.
        $success = static::flashes($client)['success'];
        $this->assertSame('La demande de gel a été annulée !', end($success));
        $this->assertFalse(static::reloaded($membership)->getFrozenChange());
    }

    public function testAFrozenMemberAsksToBeUnfrozenAtTheEndOfTheCycle(): void
    {
        $client = static::createClient();
        $membership = static::persist(MembershipBuilder::aMembership()->frozen()->build());
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        $this->postForm($client, sprintf('/member/%d/freeze_change', $membership->getId()));

        $this->assertSame(['Le compte sera dégelé à la fin du cycle !'], static::flashes($client)['success'] ?? []);
        $reloaded = static::reloaded($membership);
        $this->assertTrue($reloaded->getFrozen());
        $this->assertTrue($reloaded->getFrozenChange());
    }

    public function testAMemberCannotAskAFreezeChangeForSomebodyElse(): void
    {
        $client = static::createClient();
        $me = static::aMembership();
        $other = static::aMembership();
        static::logIn($client, $me->getMainBeneficiary()->getUser());

        $this->postForm($client, sprintf('/member/%d/freeze_change', $other->getId()));

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertFalse(static::reloaded($other)->getFrozenChange());
    }

    public function testAManagerAsksAFreezeChangeForAMemberAndStaysOnTheirFile(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/freeze_change', $member->getId()));

        $this->assertTrue($client->getResponse()->isRedirect(sprintf('/member/%d/show', $member->getMemberNumber())));
        $this->assertTrue(static::reloaded($member)->getFrozenChange());
    }

    // --- withdrawn ---------------------------------------------------------

    public function testAUserManagerClosesAMembership(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        $manager = $this->aUserWithRoles('ROLE_USER_MANAGER');
        static::logIn($client, $manager);

        $this->postForm($client, sprintf('/member/%d/withdrawn', $member->getId()), ['withdrawn' => 1]);

        $this->assertSame(['Compte fermé !'], static::flashes($client)['success'] ?? []);
        $reloaded = static::reloaded($member);
        $this->assertTrue($reloaded->isWithdrawn());
        $this->assertEquals(new \DateTime('today'), (clone $reloaded->getWithdrawnDate())->setTime(0, 0));
        $this->assertSame($manager->getId(), $reloaded->getWithdrawnBy()->getId());
    }

    public function testClosingAClosedMembershipIsRefused(): void
    {
        $client = static::createClient();
        $member = static::persist(MembershipBuilder::aMembership()->withdrawn()->build());
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/withdrawn', $member->getId()), ['withdrawn' => 1]);

        $this->assertSame(['Ce compte est déjà fermé'], static::flashes($client)['error'] ?? []);
    }

    public function testAUserManagerReopensAMembership(): void
    {
        $client = static::createClient();
        $member = static::persist(MembershipBuilder::aMembership()->withdrawn()->build());
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/withdrawn', $member->getId()), ['withdrawn' => 0]);

        $this->assertSame(['Compte ré-ouvert !'], static::flashes($client)['success'] ?? []);
        $this->assertFalse(static::reloaded($member)->isWithdrawn());
    }

    public function testReopeningAnOpenMembershipIsRefused(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/withdrawn', $member->getId()), ['withdrawn' => 0]);

        $this->assertSame(['Ce compte est déjà ouvert'], static::flashes($client)['error'] ?? []);
    }

    public function testAMemberCannotCloseTheirOwnMembership(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        $this->postForm($client, sprintf('/member/%d/withdrawn', $membership->getId()), ['withdrawn' => 1]);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertFalse(static::reloaded($membership)->isWithdrawn());
    }

    // --- flying ------------------------------------------------------------

    public function testAUserManagerMakesAMembershipFlying(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/flying', $member->getId()), ['flying' => 1]);

        $this->assertSame(['Le compte est maintenant volant !'], static::flashes($client)['success'] ?? []);
        $this->assertTrue(static::reloaded($member)->isFlying());
    }

    public function testAUserManagerMakesAFlyingMembershipFixed(): void
    {
        $client = static::createClient();
        $member = static::persist(MembershipBuilder::aMembership()->flying()->build());
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/flying', $member->getId()), ['flying' => 0]);

        $this->assertSame(['Le compte est maintenant fixe !'], static::flashes($client)['success'] ?? []);
        $this->assertFalse(static::reloaded($member)->isFlying());
    }

    public function testMakingAFlyingMembershipFlyingIsRefused(): void
    {
        $client = static::createClient();
        $member = static::persist(MembershipBuilder::aMembership()->flying()->build());
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/flying', $member->getId()), ['flying' => 1]);

        $this->assertSame(['Ce compte est déjà volant'], static::flashes($client)['error'] ?? []);
    }

    public function testMakingAFixedMembershipFixedIsRefused(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postForm($client, sprintf('/member/%d/flying', $member->getId()), ['flying' => 0]);

        $this->assertSame(['Ce compte est déjà fixe'], static::flashes($client)['error'] ?? []);
    }

    public function testAMemberCannotMakeTheirOwnMembershipFlying(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        $this->postForm($client, sprintf('/member/%d/flying', $membership->getId()), ['flying' => 1]);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertFalse(static::reloaded($membership)->isFlying());
    }

    // --- delete ------------------------------------------------------------

    public function testASuperAdminDeletesAMembership(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        $id = $member->getId();
        static::logIn($client, $this->aUserWithRoles('ROLE_SUPER_ADMIN'));

        $this->postForm($client, sprintf('/member/%d', $id), [], 'DELETE');

        $this->assertSame(['Le membre a bien été supprimé !'], static::flashes($client)['success'] ?? []);
        $this->assertNull(static::entityManager()->getRepository(Membership::class)->find($id));
    }

    public function testAnAdminCannotDeleteAMembership(): void
    {
        $client = static::createClient();
        $member = static::aMembership();
        static::logIn($client, $this->aUserWithRoles('ROLE_ADMIN'));

        $this->postForm($client, sprintf('/member/%d', $member->getId()), [], 'DELETE');

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertNotNull(static::reloaded($member));
    }

    // --- helpers -----------------------------------------------------------

    /**
     * Posts an unnamed Symfony form ("form") with its CSRF token.
     *
     * @param array<string, int|string> $fields
     */
    private function postForm($client, string $url, array $fields = [], string $method = 'POST'): void
    {
        $client->request($method, $url, ['form' => $fields + ['_token' => static::csrfToken($client, 'form')]]);
    }

    private function aUserWithRoles(string ...$roles): User
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser(UserBuilder::aUser()->withRoles(...$roles)))
                ->build()
        )->getMainBeneficiary()->getUser();
    }
}
