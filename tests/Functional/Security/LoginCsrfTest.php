<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\FunctionalTestCase;

/**
 * The login page is never reused from the browser cache with a stale CSRF
 * token, and an expired token is explained to the user.
 *
 * @internal
 */
class LoginCsrfTest extends FunctionalTestCase
{
    public function testTheLoginPageIsNotStoredByTheBrowser(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertTrue(
            $client->getResponse()->headers->hasCacheControlDirective('no-store'),
            'The login page should be sent with Cache-Control: no-store.'
        );
    }

    public function testOtherPagesAreNotForcedToNoStore(): void
    {
        $client = static::createClient();
        $client->request('GET', '/resetting/request');

        $this->assertFalse($client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    public function testAnExpiredTokenAsksTheUserToLogInAgain(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        // Same as a page shown again from the cache, after the session is gone
        $form = $crawler->selectButton('_submit')->form([
            '_username' => 'admin',
            '_password' => 'password',
            '_csrf_token' => 'stale-token',
        ]);
        $client->submit($form);

        $this->assertTrue($client->getResponse()->isRedirect());
        $crawler = $client->followRedirect();

        $error = $crawler->filterXPath("//div[contains(concat(' ', normalize-space(@class), ' '), ' card-panel ') and contains(concat(' ', normalize-space(@class), ' '), ' error ')]");
        $this->assertCount(1, $error, 'The login page should display an error.');
        $this->assertSame('La page de connexion a expiré, merci de te reconnecter.', trim($error->text()));
    }
}
