<?php

namespace App\Tests\PHPUnit;

use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeTestHook;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs each Kernel-booted test inside a transaction rolled back once it ends,
 * so a test sees the database as setUpBeforeClass() left it, whatever the
 * tests before it wrote.
 *
 * It drives dama/doctrine-test-bundle's StaticDriver, which keeps one DBAL
 * connection alive across kernel reboots so every client a test creates
 * shares the same transaction. It replaces the bundle's own PHPUnitExtension,
 * which keeps that static connection, and its open transaction, for the
 * whole run. Here the static connection only exists while a test runs:
 *
 * - setUpBeforeClass() and tearDownAfterClass() use a regular connection and
 *   commit, which is where shared fixtures are loaded. Loading them inside a
 *   test would not work anyway: the purger truncates, and TRUNCATE commits.
 * - a class implementing SkipDatabaseRollback commits too.
 * - pure TestCase classes (no kernel) are left alone.
 */
final class DatabaseIsolationExtension implements BeforeTestHook, AfterTestHook, AfterLastTestHook
{
    /** @var bool */
    private $inTransaction = false;

    public function executeBeforeTest(string $test): void
    {
        if (!self::isolates($test)) {
            return;
        }

        StaticDriver::setKeepStaticConnections(true);
        StaticDriver::beginTransaction();
        $this->inTransaction = true;
    }

    public function executeAfterTest(string $test, float $time): void
    {
        if (!$this->inTransaction) {
            return;
        }

        StaticDriver::rollBack();
        StaticDriver::setKeepStaticConnections(false);
        $this->inTransaction = false;
    }

    public function executeAfterLastTest(): void
    {
        StaticDriver::setKeepStaticConnections(false);
    }

    private static function isolates(string $test): bool
    {
        // "Class::method" or "Class::method with data set ..."
        $class = explode('::', $test, 2)[0];

        return class_exists($class)
            && is_subclass_of($class, KernelTestCase::class)
            && !is_subclass_of($class, SkipDatabaseRollback::class);
    }
}
