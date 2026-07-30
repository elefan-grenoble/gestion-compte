<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\FunctionalTestCase;
use App\Entity\AnonymousBeneficiary;
use App\Entity\Beneficiary;
use App\Entity\Shift;
use App\Helper\SwipeCard;

/**
 * Smoke tests for routes with database fixtures loaded.
 *
 * These tests verify that main pages respond with the expected HTTP status code.
 * The 'period' fixture group is loaded once per class and includes all entities
 * (users, admins, jobs, shifts, events, opening hours, dynamic content, etc.).
 *
 * @internal
 *
 * @coversNothing
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
            'beneficiary find_member_number' => ['/beneficiary/find_member_number'],
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
            'tasks list' => ['/tasks/'],
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
        $shift = $em->getRepository(Shift::class)->findOneBy([]);
        $beneficiary = $em->getRepository(Beneficiary::class)->findOneBy([]);

        $this->assertNotNull($shift, 'Fixtures should contain at least one Shift.');
        $this->assertNotNull($beneficiary, 'Fixtures should contain at least one Beneficiary.');

        $url = sprintf('/booking/bucket/%d/show/for/%d/cycle/0', $shift->getId(), $beneficiary->getId());
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

    // -------------------------------------------------------
    // Default-deny terminal rule (#1246): previously-unannotated
    // controllers are now covered by the catch-all access_control rule.
    // -------------------------------------------------------

    /**
     * BeneficiaryController has no @Security annotations at all — it relied
     * solely on an inline denyAccessUnlessGranted() voter call. The terminal
     * rule adds a second layer that must not change the observable behavior
     * for a legitimate, already-covered anonymous request.
     */
    public function testBeneficiaryEditRequiresAuthentication(): void
    {
        $client = static::createClient();

        $em = $client->getContainer()->get('doctrine')->getManager();
        $beneficiary = $em->getRepository(Beneficiary::class)->findOneBy([]);
        $this->assertNotNull($beneficiary, 'Fixtures should contain at least one Beneficiary.');

        $client->request('GET', sprintf('/beneficiary/%d/edit', $beneficiary->getId()));

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection(),
            sprintf('Anonymous beneficiary edit should redirect, got %d.', $response->getStatusCode())
        );
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    // -------------------------------------------------------
    // Default-deny terminal rule (#1246): anonymous flows that must stay
    // reachable (magic-link / invite-code / bootstrap / badge-scan routes).
    // A redirect to /login here would mean the security.yaml whitelist
    // regex is missing or wrong.
    // -------------------------------------------------------

    /**
     * @dataProvider anonymousFlowUrlProvider
     */
    public function testAnonymousFlowUrlIsNotBlockedByFirewall(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection(),
            sprintf('URL "%s" should redirect (to homepage), got %d.', $url, $response->getStatusCode())
        );
        $this->assertStringNotContainsString(
            '/login',
            $response->headers->get('Location'),
            sprintf('URL "%s" must not be gated by the terminal access_control rule.', $url)
        );
    }

    public function anonymousFlowUrlProvider(): array
    {
        return [
            // Bootstrap route: must stay reachable even once a super-admin
            // already exists, since the controller itself no-ops in that case.
            'user install_admin' => ['/user/install_admin'],
            // Vigenère magic-link route (no token supplied here): must reach
            // the controller, which then mutes/redirects on invalid input.
            'codes close_all' => ['/codes/close_all'],
            // Badge-scan route (bogus code): must reach the controller,
            // which handles an unknown code gracefully.
            'swipe_in' => ['/sw/in/bogus-code'],
        ];
    }

    /**
     * bucket_show renders anonymously with display_names conditioned on
     * authentication (SEC.1-12) — must stay reachable without a session.
     */
    public function testBucketShowIsPubliclyReachable(): void
    {
        $client = static::createClient();

        $em = $client->getContainer()->get('doctrine')->getManager();
        $shift = $em->getRepository(Shift::class)->findOneBy([]);
        $this->assertNotNull($shift, 'Fixtures should contain at least one Shift.');

        $client->request('GET', sprintf('/booking/bucket/%d/show', $shift->getId()));

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    /**
     * Encodes a value the same way App\Helper\SwipeCard does, using the
     * SWIPE_CARD_SECRET declared in .env.test. Instantiated directly rather
     * than fetched from the container, since App\Helper\SwipeCard is not a
     * public service.
     */
    private function encodeInviteCode(string $value): string
    {
        $swipeCard = new SwipeCard('SwipeSecretToEncryptNumberInUrl');

        return $swipeCard->vigenereEncode($value);
    }

    /**
     * The anonymous membership-registration entry point: member_new skips
     * its own denyAccessUnlessGranted('create', ...) call entirely when a
     * valid invite `code` resolves to an AnonymousBeneficiary — this is the
     * highest-risk route for the terminal access_control rule to break,
     * since it has no @Security annotation and self-gates purely on this
     * business logic.
     */
    public function testMemberNewWithValidInviteCodeIsPubliclyReachable(): void
    {
        $client = static::createClient();

        $email = 'invite-' . uniqid() . '@example.test';
        $em = $client->getContainer()->get('doctrine')->getManager();
        $anonymousBeneficiary = new AnonymousBeneficiary();
        $anonymousBeneficiary->setEmail($email);
        $em->persist($anonymousBeneficiary);
        $em->flush();

        $code = $this->encodeInviteCode($email);
        $client->request('GET', '/member/new?code=' . urlencode($code));

        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'member_new with a valid invite code must render the registration form anonymously.'
        );
    }

    /**
     * Same as above for member_add_beneficiary (adding a beneficiary to an
     * existing membership via invite code) — the code path either renders
     * the form (200) or redirects to homepage on a business-rule violation
     * (BeneficiaryCanHost), never to /login.
     */
    public function testMemberAddBeneficiaryWithValidInviteCodeIsNotBlockedByFirewall(): void
    {
        $client = static::createClient();

        $em = $client->getContainer()->get('doctrine')->getManager();
        $hostBeneficiary = $em->getRepository(Beneficiary::class)->findOneBy([]);
        $this->assertNotNull($hostBeneficiary, 'Fixtures should contain at least one Beneficiary.');

        $email = 'invite-' . uniqid() . '@example.test';
        $anonymousBeneficiary = new AnonymousBeneficiary();
        $anonymousBeneficiary->setEmail($email);
        $anonymousBeneficiary->setJoinTo($hostBeneficiary);
        $em->persist($anonymousBeneficiary);
        $em->flush();

        $code = $this->encodeInviteCode($email);
        $client->request('GET', '/member/add_beneficiary?code=' . urlencode($code));

        $response = $client->getResponse();
        $isFormRendered = 200 === $response->getStatusCode();
        $isNonLoginRedirect = $response->isRedirection() && false === strpos((string) $response->headers->get('Location'), '/login');

        $this->assertTrue(
            $isFormRendered || $isNonLoginRedirect,
            sprintf('member_add_beneficiary with a valid invite code must not be gated by the firewall, got %d.', $response->getStatusCode())
        );
    }

    /**
     * set_email (the #1245 temp-email activation flow) always renders
     * beneficiary/confirm.html.twig regardless of branch taken — it must
     * never redirect to /login.
     */
    public function testSetEmailIsPubliclyReachable(): void
    {
        $client = static::createClient();

        $em = $client->getContainer()->get('doctrine')->getManager();
        $beneficiary = $em->getRepository(Beneficiary::class)->findOneBy([]);
        $this->assertNotNull($beneficiary, 'Fixtures should contain at least one Beneficiary.');

        $client->request('POST', sprintf('/member/%d/set_email', $beneficiary->getId()), [
            'email' => 'new-email-' . uniqid() . '@example.test',
        ]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }
}
