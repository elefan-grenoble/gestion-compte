<?php

namespace App\Tests\Support\Security;

use App\Entity\User;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Runs one RoleMatrix case: logs in a fresh member holding only the given
 * role and calls the route.
 *
 * For FunctionalTestCase subclasses (it uses createAuthenticatedClient() and
 * persist()). The member is written inside the test's transaction and rolled
 * back with it.
 *
 * Routes whose path takes an entity id: give real ids in $parameters. With a
 * placeholder id the ParamConverter answers 404 before a @Security annotation
 * is evaluated, so only an access_control rule (e.g. ^/admin/) could refuse.
 */
trait ChecksRoleAccess
{
    /**
     * @param int|string                $expected   RoleMatrix::DENIED, RoleMatrix::GRANTED, or an exact status code
     * @param array<string, int|string> $parameters route parameters, placeholders otherwise
     */
    protected function assertRouteAccessForRole(string $route, string $role, $expected, array $parameters = []): KernelBrowser
    {
        static::createClient();
        $user = static::aMemberWithRole($role);

        $client = static::createAuthenticatedClient($user);
        $router = $client->getContainer()->get('router');
        $routeObject = $router->getRouteCollection()->get($route);
        if (null === $routeObject) {
            throw new \InvalidArgumentException(sprintf('No route "%s".', $route));
        }

        $path = RouteRequests::path($router, $route, $parameters);
        $client->request(RouteRequests::method($routeObject), $path);

        $response = $client->getResponse();
        $status = $response->getStatusCode();
        $outcome = sprintf('%s %s as %s answered %d', RouteRequests::method($routeObject), $path, $role, $status);

        if (RoleMatrix::GRANTED === $expected) {
            $this->assertNotSame(403, $status, $outcome . ', expected access.');
            $this->assertFalse(
                $response->isRedirect('http://localhost/login'),
                $outcome . ' (redirect to the login page), expected access.'
            );
        } else {
            $this->assertSame($expected, $status, $outcome . '.');
        }

        return $client;
    }

    /**
     * A persisted member (membership, main beneficiary, user) whose user
     * holds only $role. ROLE_USER is implied for every logged-in user.
     */
    protected static function aMemberWithRole(string $role): User
    {
        $user = UserBuilder::aUser();
        if ('ROLE_USER' !== $role) {
            $user->withRoles($role);
        }

        return static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser($user))
                ->build()
        )->getMainBeneficiary()->getUser();
    }
}
