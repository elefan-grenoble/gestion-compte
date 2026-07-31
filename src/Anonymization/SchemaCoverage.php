<?php

namespace App\Anonymization;

use Doctrine\DBAL\Connection;

/**
 * Compares a live schema against the manifest.
 *
 * This is the gate that runs *before* an export is produced. If the
 * database holds anything the manifest does not classify, the export is
 * refused rather than shipped with an unreviewed column in it.
 *
 * That ordering is the whole point: a column added by a migration nobody
 * thought about becomes a blocked export, not a leak discovered later.
 */
final class SchemaCoverage
{
    /** @var Connection */
    private $connection;

    /** @var Manifest */
    private $manifest;

    public function __construct(Connection $connection, Manifest $manifest)
    {
        $this->connection = $connection;
        $this->manifest = $manifest;
    }

    /**
     * @return string[] one line per problem; empty means the schema is fully classified
     */
    public function problems(): array
    {
        return array_merge(
            $this->unclassifiedTables(),
            $this->unclassifiedColumns(),
            $this->staleEntries(),
            $this->misdeclaredViews()
        );
    }

    /**
     * @return string[]
     */
    private function unclassifiedTables(): array
    {
        $problems = [];
        foreach (array_diff($this->tables(), $this->manifest->tableNames()) as $table) {
            $problems[] = sprintf(
                'Table `%s` exists in the database but is not classified in the manifest.',
                $table
            );
        }

        return $problems;
    }

    /**
     * @return string[]
     */
    private function unclassifiedColumns(): array
    {
        $problems = [];

        foreach ($this->manifest->tablesWithStrategy(Manifest::STRATEGY_ANONYMIZE) as $table) {
            $columns = $this->columns($table);
            if ([] === $columns) {
                continue;
            }

            foreach (array_diff($columns, array_keys($this->manifest->columns($table))) as $column) {
                $problems[] = sprintf(
                    'Column `%s`.`%s` carries no rule. Decide whether it holds personal data — `keep` is a valid answer, silence is not.',
                    $table,
                    $column
                );
            }
        }

        return $problems;
    }

    /**
     * @return string[]
     */
    private function staleEntries(): array
    {
        $tables = $this->tables();
        $problems = [];

        foreach ($this->manifest->tableNames() as $table) {
            if (in_array($table, $tables, true) || $this->manifest->isOptional($table)) {
                continue;
            }

            $problems[] = sprintf('Table `%s` is classified in the manifest but does not exist in the database.', $table);
        }

        return $problems;
    }

    /**
     * A table declared as a view is left alone on the grounds that its
     * contents are a projection of tables that do get anonymized. If it
     * turns out to be a real table, that reasoning collapses and it holds
     * a frozen copy of the original data.
     *
     * @return string[]
     */
    private function misdeclaredViews(): array
    {
        $problems = [];

        foreach ($this->manifest->tablesWithStrategy(Manifest::STRATEGY_VIEW) as $table) {
            $type = $this->connection->fetchOne(
                'SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );

            if (false === $type || null === $type) {
                $problems[] = sprintf('Table `%s` is declared as a view but is absent from the database.', $table);

                continue;
            }

            if ('VIEW' !== $type) {
                $problems[] = sprintf(
                    'Table `%s` is declared as a view but the database reports it as %s, so it holds its own copy of the data and is skipped by the anonymizer.',
                    $table,
                    $type
                );
            }
        }

        return $problems;
    }

    /**
     * @return string[]
     */
    private function tables(): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'
        );
    }

    /**
     * @return string[]
     */
    private function columns(string $table): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$table]
        );
    }
}
