<?php

namespace App\Tests\Functional;

use App\DataFixtures\Purger\CustomPurger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Base class for tests that need a known database state.
 *
 * The database is purged once per class, in setUpBeforeClass(). A class that
 * needs fixtures loads them there too, after the purge:
 *
 *     public static function setUpBeforeClass(): void
 *     {
 *         parent::setUpBeforeClass();
 *         static::loadFixtures(['period']);
 *     }
 *
 * Both run outside the per-test transaction (see DatabaseIsolationExtension),
 * so they are committed and every test of the class starts from them: what a
 * test writes is rolled back when it ends.
 *
 * @internal
 *
 * @coversNothing
 */
class DatabasePrimer extends WebTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        $entityManager = self::$container->get('doctrine')->getManager();

        // The purger reports on stdout; keep it out of the test output.
        ob_start();

        try {
            (new CustomPurger($entityManager))->purge();
        } finally {
            ob_end_clean();
        }

        // A test must boot its own kernel, whose connection takes part in the
        // per-test transaction; this one does not.
        self::ensureKernelShutdown();
    }

    /**
     * Purges the database and loads the given fixture groups (all when null).
     *
     * Call it from setUpBeforeClass(), never from a test: the purger
     * truncates, and TRUNCATE commits whatever transaction is open.
     *
     * @param null|string[] $groups
     */
    protected static function loadFixtures(?array $groups = null): void
    {
        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $input = ['command' => 'doctrine:fixtures:load', '--no-interaction' => true];
        if ($groups) {
            $input['--group'] = $groups;
        }

        // The fixtures echo progress; keep it out of the test output.
        $output = new BufferedOutput();
        ob_start();

        try {
            $exitCode = $application->run(new ArrayInput($input), $output);
        } finally {
            $echoed = ob_get_clean();
        }

        if (0 !== $exitCode) {
            throw new \RuntimeException(sprintf("Loading the fixtures failed (exit code %d):\n%s%s", $exitCode, $echoed, $output->fetch()));
        }

        self::ensureKernelShutdown();
    }
}
