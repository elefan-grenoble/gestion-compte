<?php

namespace App\Tests;

use Symfony\Component\Panther\PantherTestCase;

class LoginTest extends PantherTestCase
{
    public function testLogin()
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Login')->form([
            'username' => 'admin',
            'password' => 'password',
        ]);

        $client->submit($form);

        $client->waitForNavigation();

        $this->assertSame('/', $client->getCurrentURL());
    }
}
