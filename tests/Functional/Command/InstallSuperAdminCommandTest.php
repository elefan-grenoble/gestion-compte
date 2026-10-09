<?php

namespace App\Tests\Functional\Command;

use App\Entity\User;
use App\Tests\Support\Builder\UserBuilder;

/**
 * app:user:install_super_admin, which replaces the anonymous bootstrap of
 * /user/install_admin (I-SEC-11).
 *
 * @internal
 */
class InstallSuperAdminCommandTest extends CommandTestCase
{
    public function testCreatesTheSuperAdminOfAFreshInstall(): void
    {
        $this->withEnv('SUPER_ADMIN_INITIAL_PASSWORD', 'a-strong-initial-password');

        $tester = $this->runCommand('app:user:install_super_admin');

        $this->assertSame(0, $tester->getStatusCode());
        $superAdmins = $this->superAdmins();
        $this->assertCount(1, $superAdmins);
        $this->assertSame('admin', $superAdmins[0]->getUsername());
        $this->assertTrue($superAdmins[0]->isEnabled());
        $this->assertNotEmpty($superAdmins[0]->getPassword(), 'The initial password is hashed and stored.');
    }

    public function testCreatesNothingOnceASuperAdminExists(): void
    {
        static::persist(UserBuilder::aUser()->withRoles('ROLE_SUPER_ADMIN')->build());

        $tester = $this->runCommand('app:user:install_super_admin');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertCount(1, $this->superAdmins());
    }

    /**
     * The .env.dist placeholder and well-known defaults are refused
     * (I-SEC-12: .env.dist shipped `password`).
     *
     * @dataProvider refusedPasswords
     */
    public function testRefusesAPlaceholderOrDefaultPassword(string $password): void
    {
        $this->withEnv('SUPER_ADMIN_INITIAL_PASSWORD', $password);

        $tester = $this->runCommand('app:user:install_super_admin');

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertSame([], $this->superAdmins());
    }

    /**
     * @return array<string, array{string}>
     */
    public function refusedPasswords(): array
    {
        return ['placeholder' => ['<change-me>'], 'default' => ['password'], 'empty' => ['']];
    }

    /**
     * @return User[]
     */
    private function superAdmins(): array
    {
        static::entityManager()->clear();

        return static::entityManager()->getRepository(User::class)->findByRole('ROLE_SUPER_ADMIN');
    }
}
