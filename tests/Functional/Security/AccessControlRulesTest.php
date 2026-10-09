<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Regression tests for the access_control fixes of #1256: the terminal
 * default-deny rule (C-SEC-2) and the ^/api/oauth/ rule moved before ^/api
 * (C-SEC-6).
 *
 * The access map is asked directly which rule a path falls under, since the
 * annotations on the API controllers would refuse the same requests anyway
 * and hide a rule that no longer applies.
 *
 * @internal
 */
class AccessControlRulesTest extends FunctionalTestCase
{
    /**
     * C-SEC-2: a path no rule mentions is not public by default.
     */
    public function testAPathNoRuleMentionsRequiresALogin(): void
    {
        $this->assertSame(['IS_AUTHENTICATED_REMEMBERED'], $this->rolesRequiredFor('/a/route/added/tomorrow'));
    }

    /**
     * C-SEC-2: the terminal rule only applies once every other rule had its
     * chance; it must not shadow the public ones.
     */
    public function testTheTerminalRuleComesLast(): void
    {
        $this->assertSame(['IS_AUTHENTICATED_ANONYMOUSLY'], $this->rolesRequiredFor('/login'));
        $this->assertSame(['ROLE_ADMIN_PANEL'], $this->rolesRequiredFor('/admin/'));
    }

    /**
     * C-SEC-6: before the fix, ^/api matched first and ^/api/oauth/ was never
     * reached.
     *
     * @dataProvider apiOauthPaths
     */
    public function testApiOauthRoutesRequireTheOauthLoginRole(string $path): void
    {
        $this->assertSame(['ROLE_OAUTH_LOGIN'], $this->rolesRequiredFor($path));
    }

    /**
     * @return array<string, array{string}>
     */
    public function apiOauthPaths(): array
    {
        return [
            'api_user' => ['/api/oauth/user'],
            'api_nextcloud_user' => ['/api/oauth/nextcloud_user'],
        ];
    }

    public function testTheRestOfTheApiRequiresAFullLogin(): void
    {
        $this->assertSame(['IS_AUTHENTICATED_FULLY'], $this->rolesRequiredFor('/api/v4/user'));
        $this->assertSame(['IS_AUTHENTICATED_FULLY'], $this->rolesRequiredFor('/api/swipe/in', 'POST'));
    }

    // Access to the API itself, by bearer token only since I-SEC-14: see ApiFirewallTest.

    /**
     * @return null|string[]
     */
    private function rolesRequiredFor(string $path, string $method = 'GET'): ?array
    {
        static::bootKernel();
        [$roles] = static::$container->get('security.access_map')->getPatterns(Request::create($path, $method));

        return $roles;
    }
}
