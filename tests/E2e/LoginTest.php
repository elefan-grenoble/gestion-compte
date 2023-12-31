<?php

namespace App\Tests\E2e;

use Symfony\Component\Panther\PantherTestCase;

class LoginTest extends PantherTestCase
{
    public function testLogin()
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/login');

        sleep(30);



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

        sleep(30);

//        $client->submit($form);

        // wait for navigation to finish
//        $client->waitFor('#main');

//        $this->assertSame('/', $client->getCurrentURL());
    }
}
