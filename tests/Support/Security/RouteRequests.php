<?php

namespace App\Tests\Support\Security;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Turns the application's routes into requests a test can send.
 *
 * Route parameters get placeholder values ("1" for an id, any value that
 * meets the route requirements otherwise). That is enough wherever the
 * firewall decides: access_control and the authentication entry point run on
 * kernel.request, before the ParamConverter loads anything, so an anonymous
 * request to a protected route is turned away whether or not the id exists.
 * A test that needs the controller to run with real entities passes them in
 * $parameters.
 */
final class RouteRequests
{
    /** Routes of the framework and dev tooling, not of the application. */
    private const IGNORED_ROUTE_PREFIXES = ['_profiler', '_wdt', '_preview_error'];

    /** Tried in order for a parameter with a requirement. */
    private const PLACEHOLDER_CANDIDATES = ['1', 'dummy', '2024-01-01', 'ROLE_USER'];

    /**
     * The application's routes, by name.
     *
     * @return array<string, Route>
     */
    public static function applicationRoutes(RouterInterface $router): array
    {
        $routes = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            foreach (self::IGNORED_ROUTE_PREFIXES as $prefix) {
                if (0 === strpos($name, $prefix)) {
                    continue 2;
                }
            }
            $routes[$name] = $route;
        }
        ksort($routes);

        return $routes;
    }

    /**
     * The HTTP method to call the route with: GET when it accepts it (or any
     * method), its first method otherwise. Sending a method the route does not
     * accept would stop at the router with a 405, before the firewall.
     */
    public static function method(Route $route): string
    {
        $methods = $route->getMethods();

        return (!$methods || in_array('GET', $methods, true)) ? 'GET' : $methods[0];
    }

    /**
     * The route's path with placeholder values, overridden by $parameters.
     *
     * @param array<string, int|string> $parameters
     */
    public static function path(RouterInterface $router, string $name, array $parameters = []): string
    {
        $route = $router->getRouteCollection()->get($name);
        if (null === $route) {
            throw new \InvalidArgumentException(sprintf('No route "%s".', $name));
        }

        foreach ($route->compile()->getPathVariables() as $variable) {
            if (!array_key_exists($variable, $parameters)) {
                $parameters[$variable] = self::placeholder($route, $variable);
            }
        }

        return $router->generate($name, $parameters);
    }

    private static function placeholder(Route $route, string $variable): string
    {
        $requirement = $route->getRequirement($variable);
        if (null === $requirement) {
            return '1';
        }

        foreach (self::PLACEHOLDER_CANDIDATES as $candidate) {
            if (preg_match('#^(?:' . $requirement . ')$#', $candidate)) {
                return $candidate;
            }
        }

        throw new \LogicException(sprintf('No placeholder for {%s} of route %s (requirement "%s"): add one to PLACEHOLDER_CANDIDATES.', $variable, $route->getPath(), $requirement));
    }
}
