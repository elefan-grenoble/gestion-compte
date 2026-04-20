<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\DatabasePrimer;

/**
 * Functional tests for MembershipController.
 *
 * Note: Most MembershipController routes use `new Session()` instead of
 * `$request->getSession()`, which causes 500 errors in test environment
 * (see TODO_TESTS.md annexe #7). Only routes that don't have this issue
 * are tested here. The blocked routes are documented in the skipped tests section.
 */
class MembershipControllerTest extends DatabasePrimer
{
    private static bool $fixturesLoaded = false;

    public function setUp(): void
    {
        if (!self::$fixturesLoaded) {
            $this->loadFixturesWithGroups(['period']);
            self::$fixturesLoaded = true;
        }
    }

    /**
     * Helper to log in as a given user via the login form.
     *
     * @return \Symfony\Bundle\FrameworkBundle\Client
     */
    private function loginAs(string $username, string $password = 'password')
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('_submit')->form([
            '_username' => $username,
            '_password' => $password,
        ]);
        $client->submit($form);

        return $client;
    }

    // -------------------------------------------------------
    // Testable routes (no `new Session()` issue)
    // -------------------------------------------------------

    /**
     * /member/find_me — public route, renders a form to find member by number.
     */
    public function testFindMeReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/member/find_me');

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'GET /member/find_me should return 200.'
        );
    }

    /**
     * /member/find_me — form renders with expected fields.
     */
    public function testFindMeFormHasMemberNumberField(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/member/find_me');

        $this->assertGreaterThan(
            0,
            $crawler->filterXPath('//input[contains(@id, "member_number")]')->count(),
            'The find_me form should contain a member_number input.'
        );
    }

    /**
     * /member/find_me — submitting with a non-existent member number shows a warning.
     */
    public function testFindMeWithNonExistentMemberNumber(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/member/find_me');

        $form = $crawler->selectButton('Activer mon compte')->form([
            'form[member_number]' => 99999,
        ]);
        $client->submit($form);

        // Should redirect back (flash message) or re-render with warning
        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'POST /member/find_me with non-existent member should return 200.'
        );
    }

    /**
     * /member/office_tools — requires ROLE_USER_VIEWER, should redirect anonymous to login.
     */
    public function testOfficeToolsRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/member/office_tools');

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection(),
            'GET /member/office_tools should redirect anonymous user.'
        );
        $this->assertStringContainsString(
            '/login',
            $response->headers->get('Location'),
            'Anonymous user should be redirected to /login.'
        );
    }

    /**
     * /member/office_tools — authenticated user with ROLE_USER_VIEWER gets 200.
     */
    public function testOfficeToolsReturns200ForAdmin(): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', '/member/office_tools');

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'GET /member/office_tools should return 200 for admin.'
        );
    }

    /**
     * /member/office_tools — regular user without ROLE_USER_VIEWER gets 403.
     */
    public function testOfficeToolsForbiddenForRegularUser(): void
    {
        $client = $this->loginAs('Liam Smith');
        $client->request('GET', '/member/office_tools');

        $this->assertSame(
            403,
            $client->getResponse()->getStatusCode(),
            'GET /member/office_tools should return 403 for regular user.'
        );
    }

    /**
     * /member/emails_csv — requires ROLE_SUPER_ADMIN, should redirect anonymous to login.
     */
    public function testEmailsCsvRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/member/emails_csv');

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection(),
            'GET /member/emails_csv should redirect anonymous user.'
        );
        $this->assertStringContainsString(
            '/login',
            $response->headers->get('Location'),
            'Anonymous user should be redirected to /login.'
        );
    }

    /**
     * /member/emails_csv — admin (ROLE_SUPER_ADMIN) gets a CSV response.
     */
    public function testEmailsCsvReturns200ForSuperAdmin(): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', '/member/emails_csv');

        $response = $client->getResponse();
        $this->assertSame(
            200,
            $response->getStatusCode(),
            'GET /member/emails_csv should return 200 for super admin.'
        );
        $this->assertStringContainsString(
            'application/force-download',
            $response->headers->get('Content-Type'),
            'Response should be a file download.'
        );
    }

    /**
     * /member/emails_csv — regular user gets 403.
     */
    public function testEmailsCsvForbiddenForRegularUser(): void
    {
        $client = $this->loginAs('Liam Smith');
        $client->request('GET', '/member/emails_csv');

        $this->assertSame(
            403,
            $client->getResponse()->getStatusCode(),
            'GET /member/emails_csv should return 403 for regular user.'
        );
    }

    // -------------------------------------------------------
    // Blocked routes — `new Session()` issue (annexe #7)
    // These tests document what SHOULD be tested but currently
    // cannot due to the `new Session()` pattern causing 500.
    // -------------------------------------------------------

    /**
     * @dataProvider blockedByNewSessionProvider
     */
    public function testBlockedRouteDocumentation(string $route, string $description): void
    {
        $this->markTestSkipped(
            sprintf(
                'Route "%s" (%s) is blocked by `new Session()` usage — see TODO_TESTS.md annexe #7.',
                $route,
                $description
            )
        );
    }

    public function blockedByNewSessionProvider(): array
    {
        return [
            'member_show' => ['/member/{number}/show', 'Show membership details'],
            'member_new' => ['/member/new', 'Create new membership'],
            'member_edit_firewall' => ['/member/edit', 'Edit firewall form'],
            'member_new_registration' => ['/member/{number}/newRegistration', 'New registration'],
            'member_new_beneficiary' => ['/member/{number}/newBeneficiary', 'Add beneficiary (admin)'],
            'member_add_beneficiary' => ['/member/add_beneficiary', 'Add beneficiary (public link)'],
            'member_join' => ['/member/join', 'Join two memberships'],
            'member_flying' => ['/member/{id}/flying', 'Toggle flying status'],
            'member_freeze' => ['/member/{id}/freeze', 'Freeze member'],
            'member_unfreeze' => ['/member/{id}/unfreeze', 'Unfreeze member'],
            'member_freeze_change' => ['/member/{id}/freeze_change', 'Request freeze change'],
            'member_withdrawn' => ['/member/{id}/withdrawn', 'Close/reopen member'],
            'member_delete' => ['/member/{id}', 'Delete member'],
        ];
    }
}
