<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\Security\KnownOpenVulnerability;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

/**
 * Account and role management: the super admin bootstrap (I-SEC-11), role
 * changes by GET link (I-SEC-10) and impersonation by GET link (I-SEC-1).
 *
 * The database holds no fixtures: in particular no super admin, the state
 * of a fresh install.
 *
 * @internal
 */
class UserSecurityTest extends FunctionalTestCase
{
    use KnownOpenVulnerability;

    /**
     * I-SEC-11 (SEC.2-5, SPEC.4): until a super admin exists, the first
     * visitor of /user/install_admin creates it, with the initial password
     * from the environment.
     */
    public function testAnonymousVisitorCannotCreateTheSuperAdminOfAFreshInstall(): void
    {
        $client = static::createClient();
        $this->assertSame([], $this->superAdmins(), 'Precondition: a fresh install has no super admin.');

        $client->request('GET', '/user/install_admin');

        $this->assertSecureOrKnownOpen('I-SEC-11', 'the first anonymous visitor creates the super admin of a fresh install', function () {
            $this->assertSame([], $this->superAdmins(), 'An anonymous request created a super admin.');
        });
    }

    /**
     * Once a super admin exists, the route no longer creates anything for an
     * anonymous visitor.
     */
    public function testAnonymousVisitorCannotCreateAnAdminOnceASuperAdminExists(): void
    {
        $client = static::createClient();
        static::persist(UserBuilder::aUser()->withRoles('ROLE_SUPER_ADMIN')->build());
        $usersBefore = $this->userCount();

        $client->request('GET', '/user/install_admin');

        $this->assertTrue($client->getResponse()->isRedirect('/'), 'Expected a redirect to the home page.');
        $this->assertSame($usersBefore, $this->userCount());
    }

    /**
     * I-SEC-10 (SPEC.4): roles are added by a plain GET link, so a forged
     * link opened by an admin (an image tag on any page will do) grants them.
     */
    public function testAGetLinkCannotGrantARole(): void
    {
        static::createClient();
        $superAdmin = $this->aMember('ROLE_SUPER_ADMIN');
        $target = $this->aMember();

        $client = static::createAuthenticatedClient($superAdmin);
        $client->request('GET', sprintf('/user/%d/addRole/ROLE_USER_MANAGER', $target->getId()));

        $this->assertSecureOrKnownOpen('I-SEC-10', 'a GET link opened by an admin grants a role, no CSRF token', function () use ($target) {
            $this->assertNotContains('ROLE_USER_MANAGER', $this->reloaded($target)->getRoles());
        });
    }

    /**
     * I-SEC-1 (SEC.1-2): switch_user answers a plain GET ?_login_as=, without
     * a CSRF token: a forged link opened by an admin impersonates a member.
     */
    public function testAGetLinkCannotSwitchUser(): void
    {
        static::createClient();
        $admin = $this->aMember('ROLE_ADMIN');
        $victim = $this->aMember();

        $client = static::createAuthenticatedClient($admin);
        $client->request('GET', '/?_login_as=' . urlencode($victim->getUsername()));

        $token = $client->getContainer()->get('security.token_storage')->getToken();

        $this->assertSecureOrKnownOpen('I-SEC-1', 'a GET ?_login_as= link opened by an admin impersonates a member, no CSRF token', function () use ($token, $admin) {
            $this->assertNotInstanceOf(SwitchUserToken::class, $token);
            $this->assertSame($admin->getUsername(), $token->getUsername());
        });
    }

    /**
     * Impersonation itself stays reserved to ROLE_ADMIN.
     */
    public function testAMemberCannotSwitchUser(): void
    {
        static::createClient();
        $member = $this->aMember('ROLE_USER_MANAGER');
        $victim = $this->aMember();

        $client = static::createAuthenticatedClient($member);
        $client->request('GET', '/?_login_as=' . urlencode($victim->getUsername()));

        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    private function aMember(?string $role = null): User
    {
        $user = UserBuilder::aUser();
        if (null !== $role) {
            $user->withRoles($role);
        }

        return static::persist(
            MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser($user))->build()
        )->getMainBeneficiary()->getUser();
    }

    /**
     * @return User[]
     */
    private function superAdmins(): array
    {
        static::entityManager()->clear();

        return static::entityManager()->getRepository(User::class)->findByRole('ROLE_SUPER_ADMIN');
    }

    private function userCount(): int
    {
        return static::entityManager()->getRepository(User::class)->count([]);
    }

    private function reloaded(User $user): User
    {
        $em = static::entityManager();
        $em->clear();

        return $em->find(User::class, $user->getId());
    }
}
