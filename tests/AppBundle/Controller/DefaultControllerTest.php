<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Membership;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class DefaultControllerTest extends WebTestCase
{
    public function setUp()
    {
        $this->client = static::createClient();

        $this->beneficiary = new Beneficiary();
        $this->beneficiary->setFlying(false);
        $member = new Membership();
        $member->setMainBeneficiary($this->beneficiary);
        $user = new User();
        $user->setUsername('user');
        $user->setEmail('user@test.com');
        $user->setPlainPassword('password');
        $user->setEnabled(true);
        $this->beneficiary->setUser($user);
    }

    // https://stackoverflow.com/a/44745635/4293684
    private function logIn(Beneficiary $beneficiary)
    {
        $session = $this->client->getContainer()->get('session');

        // the firewall context defaults to the firewall name
        $firewallContext = 'main';

        $token = new UsernamePasswordToken($beneficiary->getUser(), $beneficiary->getUser()->getPassword(), $firewallContext, $beneficiary->getUser()->getRoles());
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    // https://stackoverflow.com/a/33195394/4293684
    protected function createAuthorizedClient(Beneficiary $beneficiary)
    {
        $container = $this->client->getContainer();

        $session = $container->get('session');
        $userManager = $container->get('fos_user.user_manager');
        $loginManager = $container->get('fos_user.security.login_manager');
        $firewallName = $container->getParameter('fos_user.firewall_name');

        $loginManager->loginUser($firewallName, $beneficiary->getUser());

        // save the login token into the session and put it in a cookie
        $container->get('session')->set('_security_'.$firewallName, serialize($container->get('security.token_storage')->getToken()));
        $container->get('session')->save();
        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    public function testIndexAnonymous()
    {
        $crawler = $this->client->request('GET', '/');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Bienvenue sur l\'espace membre', $crawler->filter('main')->text());
        $this->assertContains('Se connecter', $crawler->filter('main')->text());
    }

    public function testIndexAuthenticated()
    {
        $this->logIn($this->beneficiary);
        // $this->createAuthorizedClient($this->beneficiary);

        $crawler = $this->client->request('GET', '/');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Bonjour', $crawler->filter('main')->text());
    }
}
