<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\FunctionalTestCase;

/**
 * The "se souvenir de moi" checkbox of the login form is honoured.
 *
 * @internal
 */
class RememberMeTest extends FunctionalTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    public function testRememberMeCookieIsSetWhenCheckboxIsTicked(): void
    {
        $client = $this->submitLoginForm(true);

        $this->assertNotNull(
            $client->getCookieJar()->get('REMEMBERME'),
            'Ticking "se souvenir de moi" should set the REMEMBERME cookie.'
        );
    }

    public function testNoRememberMeCookieWhenCheckboxIsNotTicked(): void
    {
        $client = $this->submitLoginForm(false);

        $this->assertNull(
            $client->getCookieJar()->get('REMEMBERME'),
            'Without ticking "se souvenir de moi", no REMEMBERME cookie should be set.'
        );
    }

    private function submitLoginForm(bool $rememberMe)
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('_submit')->form([
            '_username' => 'admin',
            '_password' => 'password',
        ]);
        if ($rememberMe) {
            $form['_remember_me']->tick();
        }
        $client->submit($form);

        return $client;
    }
}
