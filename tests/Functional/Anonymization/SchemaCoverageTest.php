<?php

namespace App\Tests\Functional\Anonymization;

use App\Anonymization\Manifest;
use App\Anonymization\RuleRegistry;
use App\Anonymization\SchemaCoverage;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The drift check, run at review time.
 *
 * `app:anonymize` runs exactly this check before it will touch anything,
 * so an unclassified column already blocks the export. This test exists
 * so that it also blocks the pull request that introduced the column,
 * while the person who added it is still holding it.
 *
 * Do not relax it into a warning. A silent gap here is indistinguishable
 * from a leak.
 *
 * @internal
 *
 * @coversNothing
 */
class SchemaCoverageTest extends KernelTestCase
{
    /**
     * doctrine:schema:create builds `view_abstract_registration` as an
     * ordinary table, where the migrations build it as a view. That is a
     * real defect of the local setup rather than of the manifest, and it
     * is tracked separately — so it is tolerated here, and nothing else
     * is.
     */
    private const SCHEMA_CREATE_ARTIFACT = 'view_abstract_registration';

    /** @var Connection */
    private $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::$container->get('doctrine.dbal.default_connection');
    }

    public function testTheManifestCoversTheWholeSchema(): void
    {
        $manifest = Manifest::fromFile(Manifest::defaultPath(), (new RuleRegistry())->names());
        $problems = (new SchemaCoverage($this->connection, $manifest))->problems();

        if ($this->schemaWasBuiltWithoutMigrations()) {
            $problems = array_values(array_filter($problems, static function (string $problem): bool {
                return false === strpos($problem, self::SCHEMA_CREATE_ARTIFACT);
            }));
        }

        $this->assertSame([], $problems, sprintf(
            "The database holds things %s does not classify.\n"
            . "Every column has to be given a rule before an export can be trusted — `keep` is a valid answer, silence is not.\n"
            . "This is the same check app:anonymize refuses to run without.",
            basename(Manifest::defaultPath())
        ));
    }

    private function schemaWasBuiltWithoutMigrations(): bool
    {
        return 0 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'doctrine_migration_versions'"
        );
    }
}
