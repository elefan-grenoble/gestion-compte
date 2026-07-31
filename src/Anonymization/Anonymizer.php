<?php

namespace App\Anonymization;

use Doctrine\DBAL\Connection;

/**
 * Applies a Manifest to a database connection.
 *
 * This runs in SQL rather than through the ORM on purpose. Hydrating
 * entities to rewrite a string is slow on a real dump, and — more to the
 * point — walking the object graph only reaches rows that some
 * association points at, which is how orphaned rows and users without a
 * beneficiary survived the previous implementation untouched.
 *
 * The engine knows nothing about which columns matter. That decision
 * lives entirely in config/anonymization.yaml.
 */
final class Anonymizer
{
    /** @var Connection */
    private $connection;

    /** @var Manifest */
    private $manifest;

    /** @var RuleRegistry */
    private $rules;

    public function __construct(Connection $connection, Manifest $manifest, RuleRegistry $rules)
    {
        $this->connection = $connection;
        $this->manifest = $manifest;
        $this->rules = $rules;
    }

    /**
     * @param callable|null $progress fn(string $message): void
     *
     * @return array<string, int> table name => rows affected
     */
    public function run(bool $dryRun = false, callable $progress = null): array
    {
        $report = [];
        $notify = static function (string $message) use ($progress): void {
            if (null !== $progress) {
                $progress($message);
            }
        };

        // Truncation order would otherwise have to mirror the foreign key
        // graph; the export is a batch job on a throwaway copy, so lifting
        // the constraints for the duration is simpler and safe.
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($this->manifest->tablesWithStrategy(Manifest::STRATEGY_TRUNCATE) as $table) {
                $notify(sprintf('Dropping all rows of <info>%s</info>', $table));
                $report[$table] = $dryRun
                    ? (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $this->quote($table)))
                    : (int) $this->connection->executeStatement(sprintf('DELETE FROM %s', $this->quote($table)));
            }

            foreach ($this->manifest->tablesWithStrategy(Manifest::STRATEGY_ANONYMIZE) as $table) {
                $active = $this->manifest->activeColumns($table);
                if ([] === $active) {
                    continue;
                }

                $notify(sprintf('Rewriting %d column(s) of <info>%s</info>', count($active), $table));
                $report[$table] = $this->rewrite($table, $active, $dryRun);
            }
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $report;
    }

    /**
     * @param array<string, string> $active column name => rule name
     */
    private function rewrite(string $table, array $active, bool $dryRun): int
    {
        $seeds = $this->seeds($table);

        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT id FROM %s', $this->quote($table)));
        if ([] === $rows) {
            return 0;
        }

        $assignments = implode(', ', array_map(function (string $column): string {
            return $this->quote($column) . ' = ?';
        }, array_keys($active)));

        $sql = sprintf('UPDATE %s SET %s WHERE id = ?', $this->quote($table), $assignments);

        $affected = 0;
        $this->connection->beginTransaction();
        try {
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                // Rows the seed query does not cover — a payment with no
                // registration, say — fall back to their own id. They still
                // get anonymized, just not with a matching identity.
                $seed = $seeds[$id] ?? $id;

                $parameters = [];
                foreach ($active as $rule) {
                    $parameters[] = $this->rules->value($rule, $seed, $id);
                }
                $parameters[] = $id;

                if (!$dryRun) {
                    $this->connection->executeStatement($sql, $parameters);
                }
                ++$affected;
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }

        return $affected;
    }

    /**
     * Maps row id => identity seed.
     *
     * @return array<int, int>
     */
    private function seeds(string $table): array
    {
        $query = $this->manifest->seedQuery($table);
        if (null === $query) {
            $column = $this->manifest->seedColumn($table);
            if ('id' === $column) {
                return [];
            }

            $query = sprintf('SELECT id AS id, %s AS seed FROM %s', $this->quote($column), $this->quote($table));
        }

        $seeds = [];
        foreach ($this->connection->fetchAllAssociative($query) as $row) {
            if (null !== $row['seed']) {
                $seeds[(int) $row['id']] = (int) $row['seed'];
            }
        }

        return $seeds;
    }

    private function quote(string $identifier): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new \InvalidArgumentException(sprintf('Refusing to build SQL with identifier "%s".', $identifier));
        }

        return '`' . $identifier . '`';
    }
}
