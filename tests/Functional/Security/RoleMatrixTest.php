<?php

namespace App\Tests\Functional\Security;

use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Security\ChecksRoleAccess;
use App\Tests\Support\Security\RoleMatrix;
use Symfony\Component\Yaml\Yaml;

/**
 * Which role opens which back-office page, for every role of the hierarchy.
 *
 * Each route is listed with the least role that opens it, as its @Security
 * annotation (and, under /admin/, the ROLE_ADMIN_PANEL access_control rule)
 * says. RoleMatrix expands the list into one case per role: a 403 below that
 * role, access from it upwards.
 *
 * Only routes without path parameters are listed: on a route with an id,
 * the Security annotation is evaluated after the ParamConverter, so a
 * placeholder id would answer 404 whatever the role. Such routes need real
 * entities, passed to assertRouteAccessForRole() as parameters.
 *
 * Routes whose controller calls an outside service once access is granted
 * (helloasso_browser calls the HelloAsso API) are left out.
 *
 * @internal
 */
class RoleMatrixTest extends FunctionalTestCase
{
    use ChecksRoleAccess;

    /**
     * Route name => least role that opens it.
     */
    private const LEAST_ROLE_BY_ROUTE = [
        // /admin/: ROLE_ADMIN_PANEL (access_control), then the annotation.
        'admin' => 'ROLE_ADMIN_PANEL',
        'user_index' => 'ROLE_USER_MANAGER',
        'non_member_users_list' => 'ROLE_ADMIN',
        'admin_users_list' => 'ROLE_ADMIN',
        'roles_list' => 'ROLE_ADMIN',
        'user_import_csv' => 'ROLE_SUPER_ADMIN',
        'admin_closingexception_index' => 'ROLE_ADMIN',
        'admin_event_index' => 'ROLE_PROCESS_MANAGER',
        'admin_event_kind_list' => 'ROLE_PROCESS_MANAGER',
        'admin_proxies_list' => 'ROLE_PROCESS_MANAGER',
        'admin_membershipshiftexemption_index' => 'ROLE_USER_MANAGER',
        'admin_openinghour_index' => 'ROLE_ADMIN',
        'admin_openinghour_kind_list' => 'ROLE_PROCESS_MANAGER',
        'admin_period_index' => 'ROLE_SHIFT_MANAGER',
        'admin_period_copy' => 'ROLE_ADMIN',
        'admin_shifts_generation' => 'ROLE_ADMIN',
        'admin_periodpositionfreelog_index' => 'ROLE_SHIFT_MANAGER',
        'admin_shiftexemption_index' => 'ROLE_ADMIN',
        'admin_shiftfreelog_index' => 'ROLE_SHIFT_MANAGER',
        'client_list' => 'ROLE_SUPER_ADMIN',
        'client_new' => 'ROLE_ADMIN',
        'formation_list' => 'ROLE_ADMIN',
        'job_list' => 'ROLE_ADMIN',
        'job_widget_generator' => 'ROLE_PROCESS_MANAGER',
        'mail_edit' => 'ROLE_USER_MANAGER',
        'admin_socialnetwork_list' => 'ROLE_ADMIN',

        // Elsewhere: the annotation alone.
        'ambassador_noregistration_list' => 'ROLE_USER_VIEWER',
        'ambassador_lateregistration_list' => 'ROLE_USER_VIEWER',
        'ambassador_shifttimelog_list' => 'ROLE_USER_MANAGER',
        'booking_admin' => 'ROLE_SHIFT_MANAGER',
        'admin_commissions' => 'ROLE_ADMIN',
        'commission_new' => 'ROLE_SUPER_ADMIN',
        'dynamic_content_list' => 'ROLE_PROCESS_MANAGER',
        'email_template_list' => 'ROLE_PROCESS_MANAGER',
        'email_template_new' => 'ROLE_PROCESS_MANAGER',
        'helloasso_payments' => 'ROLE_FINANCE_MANAGER',
        'member_edit_firewall' => 'ROLE_USER_VIEWER',
        'member_join' => 'ROLE_ADMIN',
        'user_office_tools' => 'ROLE_USER_VIEWER',
        'admin_emails_csv' => 'ROLE_SUPER_ADMIN',
        'process_update_new' => 'ROLE_PROCESS_MANAGER',
        'registrations' => 'ROLE_FINANCE_MANAGER',
        'service_list' => 'ROLE_SUPER_ADMIN',
        'service_new' => 'ROLE_SUPER_ADMIN',
        'shift_new' => 'ROLE_SHIFT_MANAGER',
        'user_quick_new' => 'ROLE_USER_VIEWER',
        'pre_user_index' => 'ROLE_USER_VIEWER',

        // Member pages: any logged-in user.
        'event_index' => 'ROLE_USER',
        'process_update_list' => 'ROLE_USER',
    ];

    /**
     * @dataProvider roleCases
     *
     * @param int|string $expected
     */
    public function testRouteAccessForRole(string $route, string $role, $expected): void
    {
        $this->assertRouteAccessForRole($route, $role, $expected);
    }

    /**
     * @return array<string, array{string, string, int|string}>
     */
    public function roleCases(): array
    {
        return RoleMatrix::cases(self::LEAST_ROLE_BY_ROUTE);
    }

    /**
     * Every /admin/ route refuses a plain member, ids or not: the
     * access_control rule answers before the ParamConverter.
     *
     * @dataProvider adminRoutes
     */
    public function testAdminRouteIsForbiddenToAPlainMember(string $route): void
    {
        $this->assertRouteAccessForRole($route, 'ROLE_USER', RoleMatrix::DENIED);
    }

    /**
     * @return array<string, array{string}>
     */
    public function adminRoutes(): array
    {
        // Data providers run before any test, hence a kernel of their own.
        static::bootKernel();
        $routes = static::$container->get('router')->getRouteCollection()->all();
        static::ensureKernelShutdown();

        $cases = [];
        foreach ($routes as $name => $route) {
            if (0 === strpos($route->getPath(), '/admin/')) {
                $cases[$name] = [$name];
            }
        }
        ksort($cases);

        return $cases;
    }

    public function testTheMatrixCoversEveryRole(): void
    {
        $roles = array_merge(['ROLE_USER'], array_keys(Yaml::parseFile(
            dirname(__DIR__, 3) . '/config/packages/security.yaml'
        )['security']['role_hierarchy']));

        $this->assertEqualsCanonicalizing(
            array_values(array_diff($roles, ['ROLE_OAUTH_LOGIN'])),
            RoleMatrix::ROLES,
            'RoleMatrix::ROLES must list every back-office role of the hierarchy.'
        );
    }
}
