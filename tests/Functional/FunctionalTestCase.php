<?php

namespace App\Tests\Functional;

/**
 * Base class for functional tests that need database fixtures and login helpers.
 *
 * Extends DatabasePrimer (which handles DB purge and fixture loading)
 * and adds shared helper methods used across functional test classes.
 */
class FunctionalTestCase extends DatabasePrimer
{
    /**
     * Helper to log in as a given user via the login form.
     *
     * @return \Symfony\Bundle\FrameworkBundle\Client
     */
    protected function loginAs(string $username, string $password = 'password')
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
}
