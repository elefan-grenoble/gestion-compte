<?php

namespace App\Tests\Functional\Security;

use App\Entity\AccessToken;
use App\Entity\Client;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;

/**
 * The /api routes, read by the OAuth clients of the cooperative (Nextcloud,
 * GitLab) with a bearer token. I-SEC-14: the api firewall (stateless, OAuth
 * only) was declared after main, which caught /api and accepted the session
 * cookie as well.
 *
 * @internal
 */
class ApiFirewallTest extends FunctionalTestCase
{
    /**
     * @dataProvider apiUserRoutes
     */
    public function testAnOauthClientReadsTheUserWithABearerToken(string $path): void
    {
        $client = static::createClient();
        $user = $this->aMember();
        $token = $this->anAccessTokenFor($user);

        $client->request('GET', $path, [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $this->assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $this->assertStringContainsString($user->getEmail(), $client->getResponse()->getContent());
    }

    /**
     * @return array<string, array{string}>
     */
    public function apiUserRoutes(): array
    {
        return ['generic' => ['/api/oauth/user'], 'nextcloud' => ['/api/oauth/nextcloud_user'], 'gitlab' => ['/api/v4/user']];
    }

    /**
     * The OAuth routes need the oauth_login scope (ROLE_OAUTH_LOGIN), which
     * a token granted without it lacks.
     */
    public function testATokenWithoutTheOauthLoginScopeIsRefused(): void
    {
        $client = static::createClient();
        $token = $this->anAccessTokenFor($this->aMember(), null);

        $client->request('GET', '/api/oauth/user', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testAnInvalidBearerTokenIsRefused(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/oauth/user', [], [], ['HTTP_AUTHORIZATION' => 'Bearer not-a-token']);

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    /**
     * A logged-in browser does not reach the API with its session cookie.
     */
    public function testTheSessionCookieDoesNotOpenTheApi(): void
    {
        static::createClient();
        $client = static::createAuthenticatedClient($this->aMember());

        $client->request('GET', '/api/v4/user');

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    /**
     * The token endpoint stays reachable without session (its firewall,
     * security: false, now comes before main).
     */
    public function testAnOauthClientGetsAnAccessToken(): void
    {
        $client = static::createClient();
        $oauthClient = new Client();
        $oauthClient->setRedirectUris(['https://nextcloud.example.test/callback']);
        $oauthClient->setAllowedGrantTypes(['client_credentials']);
        static::persist($oauthClient);

        $client->request('POST', '/oauth/v2/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $oauthClient->getPublicId(),
            'client_secret' => $oauthClient->getSecret(),
        ]);

        $this->assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $this->assertArrayHasKey('access_token', json_decode($client->getResponse()->getContent(), true));
    }

    /**
     * The authorization page of the SSO flow still uses the session of the
     * main firewall: a logged-in member is asked to authorize the client.
     */
    public function testALoggedInMemberIsAskedToAuthorizeAClient(): void
    {
        static::createClient();
        $oauthClient = new Client();
        $oauthClient->setRedirectUris(['https://nextcloud.example.test/callback']);
        $oauthClient->setAllowedGrantTypes(['authorization_code']);
        static::persist($oauthClient);
        $client = static::createAuthenticatedClient($this->aMember());

        $client->request('GET', '/oauth/v2/auth', [
            'client_id' => $oauthClient->getPublicId(),
            'response_type' => 'code',
            'redirect_uri' => 'https://nextcloud.example.test/callback',
        ]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    private function aMember(): User
    {
        return static::persist(
            MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser(UserBuilder::aUser()))->build()
        )->getMainBeneficiary()->getUser();
    }

    private function anAccessTokenFor(User $user, ?string $scope = 'oauth_login'): string
    {
        $client = new Client();
        $client->setRedirectUris(['https://nextcloud.example.test/callback']);
        $client->setAllowedGrantTypes(['authorization_code']);
        static::persist($client);

        $token = new AccessToken();
        $token->setClient($client);
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(16)));
        $token->setExpiresAt(time() + 3600);
        $token->setScope($scope);

        static::persist($token);

        return $token->getToken();
    }
}
