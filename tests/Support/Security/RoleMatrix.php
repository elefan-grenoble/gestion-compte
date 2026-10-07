<?php

namespace App\Tests\Support\Security;

use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds route × role × expected-outcome cases for authorization tests.
 *
 * A test lists, for each route, the least role that opens it (as its
 * Security annotation or access_control rule says); cases() expands that
 * into one case per role, expecting a 403 for the roles that do not reach it
 * through the role hierarchy of config/packages/security.yaml and access for
 * the others:
 *
 *     public function roleCases(): array
 *     {
 *         return RoleMatrix::cases(['registrations' => 'ROLE_FINANCE_MANAGER']);
 *     }
 *
 *     // @dataProvider roleCases
 *     public function testRoleAccess(string $route, string $role, $expected): void
 *     {
 *         $this->assertRouteAccessForRole($route, $role, $expected);   // ChecksRoleAccess
 *     }
 *
 * The hierarchy is read from the YAML file rather than from the container so
 * that data providers, which run before any kernel boots, can use it.
 */
final class RoleMatrix
{
    /** The role is refused: a 403, the user being logged in. */
    public const DENIED = 403;

    /** The role gets through: neither a 403 nor a redirect to the login page. */
    public const GRANTED = 'granted';

    /** Every role the application grants, from the least to the most privileged. */
    public const ROLES = [
        'ROLE_USER',
        'ROLE_ADMIN_PANEL',
        'ROLE_USER_VIEWER',
        'ROLE_USER_MANAGER',
        'ROLE_SHIFT_MANAGER',
        'ROLE_FINANCE_MANAGER',
        'ROLE_PROCESS_MANAGER',
        'ROLE_ADMIN',
        'ROLE_SUPER_ADMIN',
    ];

    /** @var null|RoleHierarchy */
    private static $hierarchy;

    /**
     * @param array<string, string> $leastRoleByRoute route name => least role that opens it
     * @param string[]              $roles            the roles to try each route with
     *
     * @return array<string, array{string, string, int|string}> "route as ROLE" => [route, role, expected]
     */
    public static function cases(array $leastRoleByRoute, array $roles = self::ROLES): array
    {
        $cases = [];
        foreach ($leastRoleByRoute as $route => $leastRole) {
            foreach ($roles as $role) {
                $cases[sprintf('%s as %s', $route, $role)] = [
                    $route,
                    $role,
                    self::reaches($role, $leastRole) ? self::GRANTED : self::DENIED,
                ];
            }
        }

        return $cases;
    }

    /**
     * Whether a user holding $role is granted $required through the hierarchy.
     */
    public static function reaches(string $role, string $required): bool
    {
        return in_array($required, self::hierarchy()->getReachableRoleNames([$role]), true);
    }

    private static function hierarchy(): RoleHierarchy
    {
        if (null === self::$hierarchy) {
            $config = Yaml::parseFile(dirname(__DIR__, 3) . '/config/packages/security.yaml');
            $map = array_map(
                static function ($roles): array {
                    return (array) $roles;
                },
                $config['security']['role_hierarchy']
            );
            self::$hierarchy = new RoleHierarchy($map);
        }

        return self::$hierarchy;
    }
}
