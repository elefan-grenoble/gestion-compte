<?php

namespace App\Tests\Functional\Support;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;

/**
 * Guards FunctionalTestCase::createAuthenticatedClient(), the login helper
 * the functional tests rely on instead of the login form.
 *
 * @internal
 */
class LoginHelperTest extends FunctionalTestCase
{
    public function testAnonymousVisitorIsSentToTheLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/');

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
    }

    public function testAUserBuiltInTheTestCanBeLoggedIn(): void
    {
        static::createClient();
        $admin = static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser(UserBuilder::aUser()->withRoles('ROLE_ADMIN')))
                ->build()
        )->getMainBeneficiary()->getUser();

        $client = static::createAuthenticatedClient($admin);
        $client->request('GET', '/admin/');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testTheLoginHoldsAcrossRequests(): void
    {
        static::createClient();
        $username = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary()->getUser()->getUsername();

        $client = static::createAuthenticatedClient($username);
        $client->request('GET', '/');
        $client->request('GET', '/admin/');

        // Logged in, but without an admin role: refused rather than sent to /login.
        $this->assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testAnUnknownUsernameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        static::createAuthenticatedClient('nobody-by-that-name');
    }
}
