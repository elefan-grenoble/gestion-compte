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

        $this->assertSame([], $problems, sprintf(
            "The database holds things %s does not classify.\n"
            . "Every column has to be given a rule before an export can be trusted — `keep` is a valid answer, silence is not.\n"
            . 'This is the same check app:anonymize refuses to run without.',
            basename(Manifest::defaultPath())
        ));
    }
}
