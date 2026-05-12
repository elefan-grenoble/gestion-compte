<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\FunctionalTestCase;

/**
 * Smoke tests for routes with database fixtures loaded.
 *
 * These tests verify that main pages respond with the expected HTTP status code.
 * The 'period' fixture group is loaded once per class and includes all entities
 * (users, admins, jobs, shifts, events, opening hours, dynamic content, etc.).
 */
class SmokeTest extends FunctionalTestCase
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
    // Public routes (no authentication required)
    // -------------------------------------------------------

    /**
     * @dataProvider publicUrlProvider
     */
    public function testPublicUrlReturns200(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('Public URL "%s" did not return 200.', $url)
        );
    }

    public function publicUrlProvider(): array
    {
        return [
            'login' => ['/login'],
            'resetting request' => ['/resetting/request'],
            'about' => ['/about'],
            'homepage (anonymous)' => ['/'],
            'member find_me' => ['/member/find_me'],
        ];
    }

    // -------------------------------------------------------
    // Widget routes (public, DB needed)
    // -------------------------------------------------------

    /**
     * @dataProvider widgetUrlProvider
     */
    public function testWidgetUrlReturns200(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('Widget URL "%s" did not return 200.', $url)
        );
    }

    public function widgetUrlProvider(): array
    {
        return [
            'event widget' => ['/events/widget'],
            'shift widget' => ['/shift/widget'],
            'opening hour widget' => ['/openinghours/widget'],
            'closing exception widget' => ['/closingexceptions/widget'],
        ];
    }

    // -------------------------------------------------------
    // Redirects
    // -------------------------------------------------------

    public function testCardReaderRedirectRoute(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cardReader');

        $response = $client->getResponse();
        $this->assertTrue($response->isRedirection());
        $this->assertStringContainsString('/card_reader', $response->headers->get('Location'));
    }

    // -------------------------------------------------------
    // Authentication: login flow
    // -------------------------------------------------------

    public function testLoginFormRendersCorrectly(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertGreaterThan(0, $crawler->filterXPath('//form')->count(), 'Login page should contain a form.');
    }

    public function testLoginWithValidCredentials(): void
    {
        $client = $this->loginAs('admin');

        $this->assertTrue(
            $client->getResponse()->isRedirect(),
            'Successful login should redirect.'
        );
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('_submit')->form([
            '_username' => 'nonexistent',
            '_password' => 'wrongpassword',
        ]);
        $client->submit($form);

        // Failed login redirects back to login
        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();
        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    // -------------------------------------------------------
    // Protected routes: anonymous → redirect to login
    // -------------------------------------------------------

    /**
     * @dataProvider protectedUrlProvider
     */
    public function testProtectedUrlRedirectsAnonymousToLogin(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $response = $client->getResponse();

        $this->assertTrue(
            $response->isRedirection(),
            sprintf('Protected URL "%s" should redirect, got %d.', $url, $response->getStatusCode())
        );
        $this->assertStringContainsString(
            '/login',
            $response->headers->get('Location'),
            sprintf('Protected URL "%s" should redirect to /login.', $url)
        );
    }

    public function protectedUrlProvider(): array
    {
        return [
            'admin dashboard' => ['/admin/'],
            'profile' => ['/profile/'],
            'schedule' => ['/schedule'],
            'event index' => ['/events/'],
        ];
    }

    // -------------------------------------------------------
    // Authenticated routes — admin
    // -------------------------------------------------------

    /**
     * @dataProvider adminUrlProvider
     */
    public function testAdminUrlReturns200(string $url): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', $url);

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('Admin URL "%s" did not return 200.', $url)
        );
    }

    public function adminUrlProvider(): array
    {
        return [
            'admin dashboard' => ['/admin/'],
            'admin users' => ['/admin/users'],
            'admin non-member users' => ['/admin/non_member_users'],
            'admin admin users' => ['/admin/admin_users'],
            'admin roles' => ['/admin/roles'],
            'admin events' => ['/admin/events/'],
            'admin events list' => ['/admin/events/list'],
            'admin event kinds' => ['/admin/events/kinds/'],
            'admin closing exceptions' => ['/admin/closingexceptions/'],
            'admin closing exceptions list' => ['/admin/closingexceptions/list'],
            'admin opening hours' => ['/admin/openinghours/'],
            'admin opening hour kinds' => ['/admin/openinghours/kinds/'],
            'admin periods' => ['/admin/period/'],
            'admin shift exemptions' => ['/admin/shifts/exemptions/'],
            'admin shift free logs' => ['/admin/shifts/freelogs/'],
            'admin clients' => ['/admin/clients/'],
            'admin formations' => ['/admin/formations/'],
            'admin jobs' => ['/admin/job/'],
            'admin social networks' => ['/admin/socialnetworks/'],
        ];
    }

    // -------------------------------------------------------
    // Authenticated routes — regular user (ROLE_USER)
    // -------------------------------------------------------

    /**
     * @dataProvider regularUserUrlProvider
     */
    public function testRegularUserUrlReturns200(string $url): void
    {
        $client = $this->loginAs('Liam Smith');
        $client->request('GET', $url);

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('Regular user URL "%s" did not return 200.', $url)
        );
    }

    public function regularUserUrlProvider(): array
    {
        return [
            'homepage authenticated' => ['/'],
            'profile' => ['/profile/'],
            'schedule' => ['/schedule'],
            'events' => ['/events/'],
            'period index' => ['/period/'],
            'tasks list' => ['/tasks/'],
            'process updates' => ['/process/updates/'],
            'booking' => ['/booking/'],
            'card reader' => ['/card_reader/'],
        ];
    }

    // -------------------------------------------------------
    // Authenticated routes — staff (requires elevated role)
    // -------------------------------------------------------

    /**
     * @dataProvider staffUrlProvider
     */
    public function testStaffUrlReturns200(string $url): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', $url);

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('Staff URL "%s" did not return 200.', $url)
        );
    }

    public function staffUrlProvider(): array
    {
        return [
            'commissions' => ['/commissions/'],
            'services' => ['/services/'],
            'dynamic content list' => ['/content/'],
            'email templates' => ['/emailTemplate/'],
            'registrations' => ['/registrations/'],
            'member office_tools' => ['/member/office_tools'],
            'member emails_csv' => ['/member/emails_csv'],
        ];
    }

    // -------------------------------------------------------
    // AJAX routes — authentication required
    // -------------------------------------------------------

    /**
     * AJAX-only partials (e.g. bucket_show_for_beneficiary, fetched from
     * the booking page) must require authentication too — an anonymous
     * XMLHttpRequest should be redirected to /login like any other
     * protected route.
     */
    public function testAjaxBucketShowRequiresAuthentication(): void
    {
        $client = static::createClient();

        $em = $client->getContainer()->get('doctrine')->getManager();
        $shift = $em->getRepository(\App\Entity\Shift::class)->findOneBy([]);
        $beneficiary = $em->getRepository(\App\Entity\Beneficiary::class)->findOneBy([]);

        $this->assertNotNull($shift, 'Fixtures should contain at least one Shift.');
        $this->assertNotNull($beneficiary, 'Fixtures should contain at least one Beneficiary.');

        $url = sprintf('/bucket/%d/show/for/%d/cycle/0', $shift->getId(), $beneficiary->getId());
        $client->xmlHttpRequest('GET', $url);

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection(),
            sprintf('Anonymous AJAX call to "%s" should redirect, got %d.', $url, $response->getStatusCode())
        );
        $this->assertStringContainsString(
            '/login',
            $response->headers->get('Location'),
            'Anonymous AJAX call should redirect to /login.'
        );
    }

    /**
     * Same AJAX endpoint, but with a logged-in user — should return 200.
     */
    public function testAjaxBucketShowReturns200WhenAuthenticated(): void
    {
        $client = $this->loginAs('Liam Smith');

        $em = $client->getContainer()->get('doctrine')->getManager();
        $shift = $em->getRepository(\App\Entity\Shift::class)->findOneBy([]);
        $beneficiary = $em->getRepository(\App\Entity\Beneficiary::class)->findOneBy([]);

        $url = sprintf('/bucket/%d/show/for/%d/cycle/0', $shift->getId(), $beneficiary->getId());
        $client->xmlHttpRequest('GET', $url);

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('Authenticated AJAX call to "%s" did not return 200.', $url)
        );
    }

    /**
     * /codes/ redirects to homepage when no codes exist in DB.
     */
    public function testCodesRedirectsWhenEmpty(): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', '/codes/');

        $this->assertTrue(
            $client->getResponse()->isRedirection(),
            'GET /codes/ should redirect when no codes exist.'
        );
    }

    // -------------------------------------------------------
    // Authorization: regular user cannot access admin
    // -------------------------------------------------------

    /**
     * @dataProvider adminUrlProvider
     */
    public function testAdminUrlForbiddenForRegularUser(string $url): void
    {
        $client = $this->loginAs('Liam Smith');
        $client->request('GET', $url);

        $this->assertSame(
            403,
            $client->getResponse()->getStatusCode(),
            sprintf('Admin URL "%s" should be forbidden for regular user.', $url)
        );
    }
}
