<?php

namespace App\Tests\PHPUnit;

/**
 * Opts a test class out of the per-test transaction that
 * DatabaseIsolationExtension wraps around every Kernel-booted test.
 *
 * Only for tests that must commit, typically because they run DDL on the
 * default connection: MariaDB commits implicitly on ALTER, CREATE, DROP or
 * TRUNCATE, which leaves nothing to roll back and makes PDO throw "There is
 * no active transaction" on PHP 8. Such a test owns its cleanup.
 */
interface SkipDatabaseRollback {}
