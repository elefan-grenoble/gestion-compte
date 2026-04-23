<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\FunctionalTestCase;

/**
 * Functional tests for MembershipController.
 */
class MembershipControllerTest extends FunctionalTestCase
{
    private static bool $fixturesLoaded = false;

    public function setUp(): void
    {
        if (!self::$fixturesLoaded) {
            $this->loadFixturesWithGroups(['period']);
            self::$fixturesLoaded = true;
        }
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
    // GET routes that require admin authentication
    // -------------------------------------------------------

    /**
     * @dataProvider adminGetRouteProvider
     */
    public function testAdminGetRouteReturns200(string $url): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', $url);

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('GET %s should return 200 for admin.', $url)
        );
    }

    public function adminGetRouteProvider(): array
    {
        return [
            'member_show' => ['/member/1/show'],
            'member_edit_firewall' => ['/member/edit'],
            'member_join' => ['/member/join'],
        ];
    }

    /**
     * Routes that redirect to the show page on GET (form is embedded in show page).
     *
     * @dataProvider adminRedirectRouteProvider
     */
    public function testAdminGetRouteRedirects(string $url): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', $url);

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection(),
            sprintf('GET %s should redirect (302).', $url)
        );
    }

    public function adminRedirectRouteProvider(): array
    {
        return [
            'member_new_registration' => ['/member/1/newRegistration'],
            'member_new_beneficiary' => ['/member/1/newBeneficiary'],
        ];
    }

    /**
     * /member/new — renders the new membership form for admin.
     */
    public function testMemberNewReturns200ForAdmin(): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', '/member/new');

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'GET /member/new should return 200 for admin.'
        );
    }

    /**
     * /member/add_beneficiary — without code, throws AccessDeniedException.
     */
    public function testAddBeneficiaryDeniedWithoutCode(): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', '/member/add_beneficiary');

        $this->assertSame(
            403,
            $client->getResponse()->getStatusCode(),
            'GET /member/add_beneficiary without code should return 403.'
        );
    }

    // -------------------------------------------------------
    // POST/DELETE-only routes — GET returns 405
    // -------------------------------------------------------

    /**
     * @dataProvider postOnlyRouteProvider
     */
    public function testPostOnlyRouteReturns405OnGet(string $url): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', $url);

        $this->assertSame(
            405,
            $client->getResponse()->getStatusCode(),
            sprintf('GET %s should return 405 (Method Not Allowed).', $url)
        );
    }

    public function postOnlyRouteProvider(): array
    {
        return [
            'member_flying' => ['/member/1/flying'],
            'member_freeze' => ['/member/1/freeze'],
            'member_unfreeze' => ['/member/1/unfreeze'],
            'member_freeze_change' => ['/member/1/freeze_change'],
            'member_withdrawn' => ['/member/1/withdrawn'],
        ];
    }

    /**
     * /member/{id} DELETE — GET returns 405.
     */
    public function testMemberDeleteReturns405OnGet(): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', '/member/1');

        $this->assertSame(
            405,
            $client->getResponse()->getStatusCode(),
            'GET /member/{id} should return 405 (DELETE only).'
        );
    }
}
