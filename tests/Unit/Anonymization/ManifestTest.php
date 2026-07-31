<?php

namespace App\Tests\Unit\Anonymization;

use App\Anonymization\Manifest;
use App\Anonymization\RuleRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ManifestTest extends TestCase
{
    public function testTheRealManifestIsValid(): void
    {
        $manifest = Manifest::fromFile(Manifest::defaultPath(), (new RuleRegistry())->names());

        $this->assertNotEmpty($manifest->tableNames());
    }

    /**
     * Losing this would let a hand-written manifest reference a rule that
     * does not exist, and the column would silently never be rewritten.
     */
    public function testUnknownRuleIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown rule "obfuscate"');

        $this->build(['fos_user' => ['strategy' => 'anonymize', 'columns' => ['id' => 'keep', 'email' => 'obfuscate']]]);
    }

    public function testUnknownStrategyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('strategy must be one of');

        $this->build(['fos_user' => ['strategy' => 'scramble']]);
    }

    /**
     * Catches a typo in a key name, which would otherwise be ignored and
     * leave the column carrying its default behaviour.
     */
    public function testUnknownKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown key(s) colums');

        $this->build(['fos_user' => ['strategy' => 'anonymize', 'colums' => ['id' => 'keep']]]);
    }

    public function testAnonymizedTableMustListColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must list its columns');

        $this->build(['fos_user' => ['strategy' => 'anonymize']]);
    }

    public function testTruncatedTableMustNotListColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not list columns');

        $this->build(['note' => ['strategy' => 'truncate', 'columns' => ['id' => 'keep']]]);
    }

    /**
     * Rows are addressed by id when rewritten; a table with work to do and
     * no id would be skipped without anyone noticing.
     */
    public function testTableWithRulesButNoIdIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no "id" column');

        $this->build(['tasks_beneficiaries' => ['strategy' => 'anonymize', 'columns' => ['task_id' => 'keep', 'label' => 'word']]]);
    }

    public function testJoinTableWithoutIdIsAllowedWhenEverythingIsKept(): void
    {
        $manifest = $this->build(['tasks_beneficiaries' => ['strategy' => 'anonymize', 'columns' => ['task_id' => 'keep', 'beneficiary_id' => 'keep']]]);

        $this->assertSame([], $manifest->activeColumns('tasks_beneficiaries'));
    }

    public function testActiveColumnsExcludesKeptOnes(): void
    {
        $manifest = $this->build(['fos_user' => ['strategy' => 'anonymize', 'columns' => ['id' => 'keep', 'email' => 'email', 'roles' => 'keep']]]);

        $this->assertSame(['email' => 'email'], $manifest->activeColumns('fos_user'));
    }

    public function testSeedDefaultsToIdAndCanBeOverridden(): void
    {
        $manifest = $this->build([
            'fos_user' => ['strategy' => 'anonymize', 'columns' => ['id' => 'keep', 'email' => 'email']],
            'beneficiary' => ['strategy' => 'anonymize', 'seed' => 'user_id', 'columns' => ['id' => 'keep', 'firstname' => 'firstname']],
        ]);

        $this->assertSame('id', $manifest->seedColumn('fos_user'));
        $this->assertSame('user_id', $manifest->seedColumn('beneficiary'));
    }

    private function build(array $tables): Manifest
    {
        return Manifest::fromArray(['tables' => $tables], (new RuleRegistry())->names());
    }
}
