<?php

namespace App\Tests\Functional\Controller;

use App\DataFixtures\FixturesConstants;
use App\Entity\Beneficiary;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * User CSV import on a database holding the "commission" fixtures.
 *
 * Split from AdminControllerTest because the two need a different starting
 * database, and fixtures can only be loaded once per class, before its tests.
 *
 * @internal
 */
class AdminControllerCommissionImportTest extends FunctionalTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['commission']);
    }

    /**
     * @dataProvider \App\Tests\Functional\Controller\AdminControllerTest::csvDelimiterProvider
     *
     * @throws \Exception
     */
    public function testCsvImportForCommissionFilledBase(string $csvPath, string $delimiter)
    {
        $client = static::createClient();

        $application = new Application($client->getKernel());
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:import:users',
            '--delimiter' => $delimiter,
            'file' => $csvPath,
            '--default_mapping' => true,
        ]);

        $output = new BufferedOutput();
        $application->run($input, $output);

        $this->assertStringContainsString('Dealing with 50 lines', $output->fetch());

        // Fetch data from the test database and assert
        $em = $client->getContainer()->get('doctrine')->getManager();
        $beneficiaries = $em->getRepository(Beneficiary::class)->findAll();
        $this->assertCount(50, $beneficiaries);

        // Count the number of links between beneficiaries and commissions
        $count = 0;
        foreach ($beneficiaries as $beneficiary) {
            $count += $beneficiary->getCommissions()->count();
        }

        $this->assertSame(self::countCommissionReferences($csvPath, $delimiter), $count);
    }

    /**
     * Number of commission ids listed in the "Commission (liste id)" column of
     * the CSV (67 in the mocks). Every id of the mocks (1 to 10) exists in the
     * "commission" fixtures (FixturesConstants::COMMISSIONS_COUNT) and none of
     * those fixtures attaches a beneficiary to a commission, so each id of the
     * CSV must give exactly one beneficiary <-> commission link after import.
     */
    private static function countCommissionReferences(string $csvPath, string $delimiter): int
    {
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle, 0, $delimiter);
        $column = array_search('Commission (liste id)', $header, true);
        self::assertNotFalse($column, 'The CSV mock has no commission column.');

        $count = 0;
        while (false !== ($row = fgetcsv($handle, 0, $delimiter))) {
            $ids = array_filter(array_map('trim', explode(',', $row[$column])), 'strlen');
            foreach ($ids as $id) {
                self::assertLessThanOrEqual(FixturesConstants::COMMISSIONS_COUNT, (int) $id, 'The CSV references a commission the fixtures do not create.');
            }
            $count += count($ids);
        }
        fclose($handle);

        return $count;
    }
}
