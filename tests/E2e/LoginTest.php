<?php

namespace App\Tests\E2e;

use Symfony\Component\Panther\PantherTestCase;

class LoginTest extends PantherTestCase
{
    public function testLogin()
    {
        $client = static::createPantherClient(
            [
                'hostname' => 'example.com',
                'port' => 8000,
            ]
        );

        $crawler = $client->request('GET', '/login');


//        $form = $crawler->selectButton('Login')->form([
//            'username' => 'admin',
//            'password' => 'password',
//        ]);

        // select the form with id "_submit" and submit it
        $form = $crawler->filter('#loginForm')->form(
            [
                '_username' => 'admin',
                '_password' => 'password',
            ]
        );

        $client->submit($form);

        // wait for navigation to finish
        $client->waitFor('#main');

        $this->assertSame('/', $client->getCurrentURL());
    }
}
