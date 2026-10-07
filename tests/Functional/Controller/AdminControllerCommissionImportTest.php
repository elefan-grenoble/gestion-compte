<?php

namespace App\Tests\Functional\Controller;

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
 *
 * @coversNothing
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

        $application->run($input, new BufferedOutput());

        // Fetch data from the test database and assert
        $em = $client->getContainer()->get('doctrine')->getManager();
        $beneficiaries = $em->getRepository(Beneficiary::class)->findAll();
        $this->assertCount(50, $beneficiaries);

        // Count the number of links between beneficiaries and commissions
        $count = 0;
        foreach ($beneficiaries as $beneficiary) {
            $count += $beneficiary->getCommissions()->count();
        }

        $this->assertEquals(67, $count);
    }
}
