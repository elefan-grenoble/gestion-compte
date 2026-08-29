<?php

namespace App\Tests\Functional\Migrations;

use app\Migrations\Version20211223205749;
use App\Tests\Functional\DatabasePrimer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Psr\Log\NullLogger;

// Migration classes live under the `app\Migrations` namespace and are loaded
// by the doctrine-migrations bundle from its own configured path, not via
// Composer's PSR-4 autoloading (which only covers `App\` -> src/) — so this
// class needs an explicit require to be usable here.
require_once dirname(__DIR__, 3) . '/src/Migrations/Version20211223205749.php';

/**
 * Regression test for the migration that used to wipe `period_position`
 * instead of migrating its data (fixed in v1.31.3, forward-ported to
 * master here).
 *
 * The test database is built by replaying every migration in order
 * (see SchemaBuiltFromMigrationsTest), so by the time this test runs
 * Version20211223205749 has already executed against an empty schema —
 * that never exercises the data-reconstruction path this migration
 * exists for. To cover it, this test rewinds just the handful of tables
 * this one migration touches back to their pre-migration shape, seeds
 * legacy-shaped rows, then replays up()/postUp() exactly the way the
 * Doctrine executor does (up() only queues SQL via addSql(), postUp()
 * runs its own statements directly) and asserts the reconstructed data.
 *
 * It deliberately does not go through `doctrine:migrations:migrate` to
 * step backwards: this migration's own down() re-adds `period.week_cycle`
 * as `LONGTEXT ... DEFAULT '0,1,2,3'`, which MariaDB rejects (the same
 * class of bug Version20210425170158 was fixed for) — a preexisting,
 * unrelated issue in a migration nobody runs down() on in practice.
 *
 * @internal
 *
 * @coversNothing
 */
class Version20211223205749Test extends DatabasePrimer
{
    private Connection $connection;

    protected function setUp(): void
    {
        $client = static::createClient();
        $this->connection = $client->getKernel()->getContainer()->get('doctrine.dbal.default_connection');
    }

    public function testUpRebuildsPeriodPositionsAndPostUpRelinksShifts(): void
    {
        $this->rewindToPreMigrationSchema();

        $now = '2024-01-01 00:00:00';

        $jobId = $this->insert('job', ['name' => 'Cuisine', 'color' => '#ffffff', 'created_at' => $now]);
        $formationId = $this->insert('formation', ['name' => 'Plonge', 'roles' => serialize([]), 'created_at' => $now]);
        $periodId = $this->insert('period', [
            'job_id' => $jobId,
            'day_of_week' => 1,
            'start' => '08:00:00',
            'end' => '10:00:00',
            // Weeks 0 and 1 -> postUp() maps them to charWeek 'A' and 'B'.
            'week_cycle' => '0,1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $legacyPositionId = $this->insert('period_position', [
            'formation_id' => $formationId,
            'nb_of_shifter' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->connection->executeStatement(
            'INSERT INTO period_position_period (period_position_id, period_id) VALUES (?, ?)',
            [$legacyPositionId, $periodId]
        );
        // ISO week 1 of 2024 -> weekCycleIndex (1 - 1) % 4 = 0 -> 'A', matching
        // the period's week 0. Same start/end time-of-day as the period.
        $shiftId = $this->insert('shift', [
            'job_id' => $jobId,
            'formation_id' => $formationId,
            'start' => '2024-01-02 08:00:00',
            'end' => '2024-01-02 10:00:00',
            'created_at' => $now,
        ]);

        $this->runMigration();

        $rebuilt = $this->connection->fetchAllAssociative(
            'SELECT id, week_cycle FROM period_position WHERE period_id = ? ORDER BY week_cycle',
            [$periodId]
        );
        // 2 weeks (A, B) x nb_of_shifter (2) = 4 reconstructed rows, one per legacy slot.
        self::assertCount(4, $rebuilt, 'period_position should be rebuilt from period_position_period, not wiped');
        self::assertSame(['A', 'A', 'B', 'B'], array_column($rebuilt, 'week_cycle'));

        $weekAIds = array_map('intval', array_column(
            array_filter($rebuilt, static fn(array $row) => 'A' === $row['week_cycle']),
            'id'
        ));

        $shiftPositionId = $this->connection->fetchOne('SELECT position_id FROM shift WHERE id = ?', [$shiftId]);
        self::assertNotFalse($shiftPositionId, 'shift should have been matched to a rebuilt period_position');
        self::assertContains((int) $shiftPositionId, $weekAIds, 'shift should be linked to a week-A position for its period');
    }

    private function runMigration(): void
    {
        $migration = new Version20211223205749($this->connection, new NullLogger());
        $schema = new Schema();

        $migration->up($schema);
        foreach ($migration->getSql() as $query) {
            $this->connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
        $migration->postUp($schema);
    }

    /**
     * Undo just this migration's schema changes, without using its own
     * down() (see class docblock for why).
     */
    private function rewindToPreMigrationSchema(): void
    {
        $this->connection->executeStatement('ALTER TABLE period ADD week_cycle LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\'');

        $this->connection->executeStatement('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45DD842E46');
        $this->connection->executeStatement('DROP INDEX IDX_A50B3B45DD842E46 ON shift');
        $this->connection->executeStatement('ALTER TABLE shift DROP position_id');

        $this->connection->executeStatement('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D496EC8B7ADE');
        $this->connection->executeStatement('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D496A7DA74C1');
        $this->connection->executeStatement('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D4968B7E4006');
        $this->connection->executeStatement('DROP INDEX IDX_2367D496EC8B7ADE ON period_position');
        $this->connection->executeStatement('DROP INDEX IDX_2367D496A7DA74C1 ON period_position');
        $this->connection->executeStatement('DROP INDEX IDX_2367D4968B7E4006 ON period_position');
        $this->connection->executeStatement('ALTER TABLE period_position ADD nb_of_shifter INT NOT NULL, DROP period_id, DROP shifter_id, DROP booker_id, DROP week_cycle, DROP booked_time');

        $this->connection->executeStatement('CREATE TABLE period_position_period (period_position_id INT NOT NULL, period_id INT NOT NULL, INDEX IDX_A0A94FFFA95DF5B1 (period_position_id), INDEX IDX_A0A94FFFEC8B7ADE (period_id), PRIMARY KEY(period_position_id, period_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->connection->executeStatement('ALTER TABLE period_position_period ADD CONSTRAINT FK_A0A94FFFA95DF5B1 FOREIGN KEY (period_position_id) REFERENCES period_position (id) ON DELETE CASCADE');
        $this->connection->executeStatement('ALTER TABLE period_position_period ADD CONSTRAINT FK_A0A94FFFEC8B7ADE FOREIGN KEY (period_id) REFERENCES period (id) ON DELETE CASCADE');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insert(string $table, array $data): int
    {
        $this->connection->insert($table, $data);

        return (int) $this->connection->lastInsertId();
    }
}
