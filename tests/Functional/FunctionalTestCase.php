<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\PersistsEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Base class for functional tests that need database fixtures and login helpers.
 *
 * Extends DatabasePrimer (which handles DB purge and fixture loading)
 * and adds shared helper methods used across functional test classes.
 *
 * @internal
 *
 * @coversNothing
 */
class FunctionalTestCase extends DatabasePrimer
{
    use PersistsEntities;

    /** Firewall the application's pages sit behind (config/packages/security.yaml). */
    private const FIREWALL = 'main';

    /**
     * Logs in through the login form, as a user would.
     *
     * Use it to test the login itself; elsewhere createAuthenticatedClient()
     * is faster and does not depend on the form.
     *
     * @return KernelBrowser
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

    /**
     * A client already logged in as the given user, without going through the
     * login form.
     *
     * @param string|User $user a User, or the username or email of one in the database
     */
    protected static function createAuthenticatedClient($user): KernelBrowser
    {
        $client = static::createClient();
        static::logIn($client, $user);

        return $client;
    }

    /**
     * Stores a security token for the given user in the client's session, the
     * Symfony 4.4 way of doing what KernelBrowser::loginUser() does from 5.1.
     *
     * The token carries the user's roles as they are now: change them before
     * logging in, not after. No login event fires, so listeners on it (the
     * last-login date for one) do not run.
     *
     * @param string|User $user a User, or the username or email of one in the database
     */
    protected static function logIn(KernelBrowser $client, $user): void
    {
        $container = $client->getContainer();

        if (!$user instanceof User) {
            $username = $user;
            $user = $container->get('fos_user.user_manager')->findUserByUsernameOrEmail($username);
            if (!$user instanceof User) {
                throw new \InvalidArgumentException(sprintf('No user "%s" in the test database.', $username));
            }
        }

        $token = new UsernamePasswordToken($user, null, self::FIREWALL, $user->getRoles());

        $session = $container->get('session');
        $session->set('_security_' . self::FIREWALL, serialize($token));
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }
}
