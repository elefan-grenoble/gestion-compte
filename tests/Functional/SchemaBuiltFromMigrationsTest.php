<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The test database has to be built the way CI and production build it:
 * by replaying the migrations.
 *
 * `doctrine:schema:create` deduces the schema from the entity mappings
 * instead, and the two do not agree. The visible case is
 * `view_abstract_registration`, which the migrations create as a SQL view
 * reading live from `beneficiary`, and which `schema:create` materializes
 * as an ordinary table — a frozen copy of the real names that nothing
 * refreshes and no anonymization pass reaches through `beneficiary`.
 *
 * A developer running against a schema built the other way can green-light
 * a migration bug locally and only find out in CI, so the divergence is
 * caught here rather than there.
 *
 * @internal
 *
 * @coversNothing
 */
class SchemaBuiltFromMigrationsTest extends KernelTestCase
{
    /**
     * The log table is named by `config/packages/doctrine_migrations.yaml`,
     * not by the bundle default — renaming it there means renaming it here.
     */
    private const MIGRATION_LOG = 'migration_versions';

    /** @var Connection */
    private $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::$container->get('doctrine.dbal.default_connection');
    }

    public function testTheSchemaWasBuiltByReplayingTheMigrations(): void
    {
        $this->assertSame(
            'BASE TABLE',
            $this->tableType(self::MIGRATION_LOG),
            "The test database has no migration log, so it was not built by the migrations.\n"
            . 'Rebuild it with `make db-reset`, which drops, creates, then replays them.'
        );
    }

    public function testAbstractRegistrationIsAViewAndNotACopy(): void
    {
        $this->assertSame(
            'VIEW',
            $this->tableType('view_abstract_registration'),
            "`view_abstract_registration` is not a view, so it holds a stale copy of the beneficiary names\n"
            . 'instead of reading them live. Rebuild the test database with `make db-reset`.'
        );
    }

    /**
     * 'BASE TABLE', 'VIEW', or '' when the schema holds no such object.
     */
    private function tableType(string $name): string
    {
        $type = $this->connection->fetchOne(
            'SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$name]
        );

        return false === $type || null === $type ? '' : (string) $type;
    }
}
