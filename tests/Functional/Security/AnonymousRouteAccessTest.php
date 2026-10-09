<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Security\RouteRequests;
use Symfony\Component\HttpFoundation\Request;

/**
 * What an anonymous visitor can reach, checked against every route the
 * application declares, so that a new public route cannot appear unnoticed.
 *
 * PUBLIC_ROUTES lists the routes access_control opens to anonymous visitors,
 * each with the reason it is public. Every other route must send an anonymous
 * visitor to the login page. Two checks enforce it:
 *
 * - testPublicRoutesAreExactlyTheWhitelist() asks the access map (the
 *   compiled access_control rules) which rule each route's path falls under,
 *   and compares the routes it opens to anonymous visitors with the list,
 *   both ways: a route opened by a rule but missing from the list fails, and
 *   so does a listed route no rule opens any more.
 * - testProtectedRouteSendsAnonymousVisitorToLogin() calls every other route
 *   anonymously and expects a redirect to the login page.
 *
 * Route parameters get placeholder values (RouteRequests): the firewall turns
 * an anonymous visitor away on kernel.request, before the ParamConverter
 * looks the id up, so no fixtures are needed. Public routes are not called:
 * what they do once reached is up to their controller, and is covered by
 * SmokeTest and the tests of each flow.
 *
 * @internal
 */
class AnonymousRouteAccessTest extends FunctionalTestCase
{
    /**
     * Routes open to anonymous visitors, mirroring the public rules of
     * access_control in config/packages/security.yaml. Adding a route here is
     * a security decision: say why it must be public, and reference the
     * finding when the route is public only because a hole is still open.
     */
    private const PUBLIC_ROUTES = [
        // Authentication and account recovery (FOSUserBundle, OAuth2 server, OIDC).
        'fos_user_security_login' => 'the login form',
        'fos_user_registration_register' => 'FOSUserBundle self-registration',
        'fos_user_registration_check_email' => 'FOSUserBundle self-registration',
        'fos_user_registration_confirm' => 'FOSUserBundle self-registration, emailed confirmation token',
        'fos_user_registration_confirmed' => 'FOSUserBundle self-registration',
        'fos_user_resetting_request' => 'password reset, the user cannot log in',
        'fos_user_resetting_send_email' => 'password reset, the user cannot log in',
        'fos_user_resetting_check_email' => 'password reset, the user cannot log in',
        'fos_user_resetting_reset' => 'password reset, emailed reset token',
        'fos_oauth_server_token' => 'OAuth2 token endpoint, authenticates clients itself',
        'fos_oauth_server_authorize' => 'OAuth2 consent page, asks for a login itself',
        'oauth_login' => 'OIDC login (Keycloak)',
        'oauth_logout' => 'OIDC logout (Keycloak)',
        'oauth_check' => 'OIDC callback (Keycloak)',

        // Public pages and embeddable widgets.
        'homepage' => 'home page, renders a public variant for anonymous visitors',
        'about' => 'public "about" page',
        'widget' => 'widget embedded in the cooperative\'s public website',
        'openinghour_widget' => 'widget embedded in the cooperative\'s public website',
        'event_widget' => 'widget embedded in the cooperative\'s public website',
        'shift_widget' => 'widget embedded in the cooperative\'s public website',
        'closingexception_widget' => 'widget embedded in the cooperative\'s public website',
        'bucket_show' => 'public shift detail, shifters\' names only shown when logged in (SEC.1-12)',
        'booking_by_day' => 'public day planning, beneficiary data gated in the controller (SEC.2-4)',

        // Onboarding of new members, driven by emailed invite codes.
        'member_new' => 'membership form reached from an emailed invite code',
        'member_add_beneficiary' => 'beneficiary form reached from an emailed invite code',
        'find_me' => 'account activation: find one\'s membership (m-SEC-10, enumeration)',
        'find_member_number' => 'account activation: find one\'s member number (m-SEC-10, enumeration)',
        'confirm' => 'account activation: confirm one\'s identity (m-SEC-10, enumeration)',
        'set_email' => 'OPEN HOLE C-SEC-1: anyone can set the email of a temp-email account',

        // Badges, card reader and emailed one-click links.
        'swipe_in' => 'badge QR code login',
        'root' => '/cardReader, legacy redirect to the card reader',
        'card_reader_index' => 'card reader kiosk, gated by the card_reader voter',
        'card_reader_check' => 'OPEN HOLE I-SEC-4: validates shifts from a badge number, no login',
        'code_change_done' => 'emailed one-click link, signed and time-limited token (C-SEC-5)',

        // Machine-to-machine and bootstrap.
        'helloasso_notify' => 'OPEN HOLE I-SEC-7: HelloAsso webhook, no authentication',
        'user_install_admin' => 'tells a fresh install to create its super admin from the command line (I-SEC-11)',
    ];

    /**
     * Protected routes a firewall listener answers before access_control is
     * consulted. They only need to redirect without rendering anything.
     */
    private const HANDLED_BEFORE_ACCESS_CONTROL = [
        'fos_user_security_logout' => 'the logout listener ends the (empty) session and redirects to the OIDC logout',
    ];

    public function testPublicRoutesAreExactlyTheWhitelist(): void
    {
        static::bootKernel();
        $router = static::$container->get('router');
        $accessMap = static::$container->get('security.access_map');

        $public = [];
        foreach (RouteRequests::applicationRoutes($router) as $name => $route) {
            $request = Request::create(RouteRequests::path($router, $name), RouteRequests::method($route));
            [$attributes] = $accessMap->getPatterns($request);

            // No matching rule means no restriction at all.
            if (null === $attributes || in_array('IS_AUTHENTICATED_ANONYMOUSLY', $attributes, true)) {
                $public[] = $name;
            }
        }

        $whitelist = array_keys(self::PUBLIC_ROUTES);
        sort($whitelist);

        $this->assertSame(
            [],
            array_values(array_diff($public, $whitelist)),
            'These routes are open to anonymous visitors but missing from PUBLIC_ROUTES: protect them, or whitelist them with a reason.'
        );
        $this->assertSame(
            [],
            array_values(array_diff($whitelist, $public)),
            'These PUBLIC_ROUTES entries are no longer public (or no longer exist): remove them from the list.'
        );
    }

    /**
     * @dataProvider protectedRoutes
     */
    public function testProtectedRouteSendsAnonymousVisitorToLogin(string $name): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get('router');
        $route = $router->getRouteCollection()->get($name);
        $method = RouteRequests::method($route);
        $path = RouteRequests::path($router, $name);

        $client->request($method, $path);

        $response = $client->getResponse();
        if (0 === strpos($path, '/api/')) {
            // The api firewall is stateless and OAuth only (I-SEC-14): no login page, a 401.
            $this->assertSame(401, $response->getStatusCode(), sprintf('Anonymous %s %s (%s) answered %d, expected 401.', $method, $path, $name, $response->getStatusCode()));

            return;
        }
        if (array_key_exists($name, self::HANDLED_BEFORE_ACCESS_CONTROL)) {
            $this->assertTrue($response->isRedirection(), sprintf('%s answered %d, expected a redirect.', $name, $response->getStatusCode()));

            return;
        }
        $this->assertTrue(
            $response->isRedirect('http://localhost/login'),
            sprintf(
                'Anonymous %s %s (%s) answered %d%s, expected a redirect to the login page.',
                $method,
                $path,
                $name,
                $response->getStatusCode(),
                $response->headers->has('Location') ? ' to ' . $response->headers->get('Location') : ''
            )
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public function protectedRoutes(): array
    {
        // Data providers run before any test, hence a kernel of their own.
        static::bootKernel();
        $names = array_keys(RouteRequests::applicationRoutes(static::$container->get('router')));
        static::ensureKernelShutdown();

        $cases = [];
        foreach ($names as $name) {
            if (!array_key_exists($name, self::PUBLIC_ROUTES)) {
                $cases[$name] = [$name];
            }
        }

        return $cases;
    }
}
