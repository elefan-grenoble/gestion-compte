<?php

namespace App\Anonymization;

use Symfony\Component\Yaml\Yaml;

/**
 * Parsed and validated form of config/anonymization.yaml.
 *
 * Validation is deliberately strict — an unknown key or an unknown rule
 * name is an error rather than something quietly ignored. A manifest that
 * silently drops a typo would hand back exactly the false confidence this
 * whole mechanism exists to remove.
 */
final class Manifest
{
    public const STRATEGY_TRUNCATE = 'truncate';
    public const STRATEGY_ANONYMIZE = 'anonymize';
    public const STRATEGY_VIEW = 'view';

    private const STRATEGIES = [self::STRATEGY_TRUNCATE, self::STRATEGY_ANONYMIZE, self::STRATEGY_VIEW];
    private const TABLE_KEYS = ['strategy', 'columns', 'reason', 'seed', 'seed_query', 'optional'];

    /** @var array<string, array<string, mixed>> */
    private $tables;

    /**
     * @param array<string, array<string, mixed>> $tables
     */
    private function __construct(array $tables)
    {
        $this->tables = $tables;
    }

    public static function defaultPath(): string
    {
        return dirname(__DIR__, 2) . '/config/anonymization.yaml';
    }

    /**
     * @param string[] $knownRules rule names accepted in the `columns` map
     */
    public static function fromFile(string $path, array $knownRules): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Anonymization manifest not found at "%s".', $path));
        }

        return self::fromArray(Yaml::parseFile($path) ?: [], $knownRules);
    }

    /**
     * @param array<string, mixed> $raw
     * @param string[]             $knownRules
     */
    public static function fromArray(array $raw, array $knownRules): self
    {
        if (!isset($raw['tables']) || !is_array($raw['tables']) || [] === $raw['tables']) {
            throw new \InvalidArgumentException('The manifest must declare a non-empty "tables" mapping.');
        }

        $tables = [];
        foreach ($raw['tables'] as $table => $definition) {
            $tables[$table] = self::validateTable((string) $table, $definition, $knownRules);
        }

        ksort($tables);

        return new self($tables);
    }

    /**
     * @param mixed    $definition
     * @param string[] $knownRules
     *
     * @return array<string, mixed>
     */
    private static function validateTable(string $table, $definition, array $knownRules): array
    {
        if (!is_array($definition)) {
            throw new \InvalidArgumentException(sprintf('Table "%s": definition must be a mapping.', $table));
        }

        $unknown = array_diff(array_keys($definition), self::TABLE_KEYS);
        if ([] !== $unknown) {
            throw new \InvalidArgumentException(sprintf('Table "%s": unknown key(s) %s.', $table, implode(', ', $unknown)));
        }

        $strategy = $definition['strategy'] ?? null;
        if (!in_array($strategy, self::STRATEGIES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Table "%s": strategy must be one of %s, got %s.',
                $table,
                implode(', ', self::STRATEGIES),
                var_export($strategy, true)
            ));
        }

        // A truncated table keeps no rows, so a column added to it later
        // carries no data and needs no classification. A view holds no
        // rows of its own at all.
        if (self::STRATEGY_ANONYMIZE !== $strategy) {
            if (isset($definition['columns'])) {
                throw new \InvalidArgumentException(sprintf('Table "%s": a "%s" table must not list columns.', $table, $strategy));
            }

            return [
                'strategy' => $strategy,
                'columns' => [],
                'seed' => 'id',
                'seed_query' => null,
                'optional' => (bool) ($definition['optional'] ?? false),
            ];
        }

        if (!isset($definition['columns']) || !is_array($definition['columns']) || [] === $definition['columns']) {
            throw new \InvalidArgumentException(sprintf('Table "%s": an "anonymize" table must list its columns.', $table));
        }

        $columns = [];
        foreach ($definition['columns'] as $column => $rule) {
            if (!is_string($rule)) {
                throw new \InvalidArgumentException(sprintf('Table "%s", column "%s": rule must be a string.', $table, $column));
            }
            if (!in_array($rule, $knownRules, true)) {
                throw new \InvalidArgumentException(sprintf('Table "%s", column "%s": unknown rule "%s".', $table, $column, $rule));
            }
            $columns[(string) $column] = $rule;
        }

        $definitionHasWork = [] !== array_filter($columns, static function (string $rule): bool {
            return RuleRegistry::KEEP !== $rule;
        });

        // Rows are rewritten one by one, addressed by `id`. Tables that
        // only ever get `keep` are never touched, so they are exempt.
        if ($definitionHasWork && !array_key_exists('id', $columns)) {
            throw new \InvalidArgumentException(sprintf('Table "%s": has rules to apply but no "id" column to address rows by.', $table));
        }

        return [
            'strategy' => $strategy,
            'columns' => $columns,
            'seed' => (string) ($definition['seed'] ?? 'id'),
            'seed_query' => isset($definition['seed_query']) ? (string) $definition['seed_query'] : null,
            'optional' => (bool) ($definition['optional'] ?? false),
        ];
    }

    /**
     * @return string[]
     */
    public function tableNames(): array
    {
        return array_keys($this->tables);
    }

    public function has(string $table): bool
    {
        return isset($this->tables[$table]);
    }

    public function strategy(string $table): string
    {
        return $this->get($table)['strategy'];
    }

    /**
     * @return array<string, string> column name => rule name
     */
    public function columns(string $table): array
    {
        return $this->get($table)['columns'];
    }

    /**
     * The columns that actually get rewritten — everything that is not `keep`.
     *
     * @return array<string, string>
     */
    public function activeColumns(string $table): array
    {
        return array_filter($this->get($table)['columns'], static function (string $rule): bool {
            return RuleRegistry::KEEP !== $rule;
        });
    }

    public function seedColumn(string $table): string
    {
        return $this->get($table)['seed'];
    }

    public function seedQuery(string $table): ?string
    {
        return $this->get($table)['seed_query'];
    }

    /**
     * Whether the table is allowed to be absent from the database.
     *
     * Only true for infrastructure tables that exist depending on how the
     * schema was built, never for anything holding application data.
     */
    public function isOptional(string $table): bool
    {
        return $this->get($table)['optional'];
    }

    /**
     * @return string[]
     */
    public function tablesWithStrategy(string $strategy): array
    {
        $matching = array_filter($this->tables, static function (array $definition) use ($strategy): bool {
            return $strategy === $definition['strategy'];
        });

        return array_keys($matching);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $table): array
    {
        if (!isset($this->tables[$table])) {
            throw new \InvalidArgumentException(sprintf('Table "%s" is not described by the manifest.', $table));
        }

        return $this->tables[$table];
    }
}
