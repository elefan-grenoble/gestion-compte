<?php

namespace App\Tests\Functionnal\Controller;

use App\Tests\Functionnal\DatabasePrimer;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class AdminControllerTest extends DatabasePrimer
{

    /**
     * @throws Exception
     */
    public function testCsvImportForEmptyBaseWithCommas()
    {
        echo "\n\033[32m testCsvImportForEmptyBaseWithCommas \033[0m\n";

        $client = static::createClient();

        // Path to the mock CSV file
        $csvPath = __DIR__ . '/../Mocks/mocked_users.csv';

        $application = new Application($client->getKernel());
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:import:users',
            '--delimiter' => ',',
            'file' => $csvPath,
            '--default_mapping' => true
        ]);

        $output = new BufferedOutput();
        $application->run($input, $output);

        $content = $output->fetch();

        // Check if the response is successful
        $this->assertTrue(strpos($content, 'Dealing with 50 lines') !== false);

        // Fetch data from the test database and assert
        $em = $client->getContainer()->get('doctrine')->getManager();
        $users = $em->getRepository('App:User')->findAll();

        // Assert that the number of users in the database matches the number in the CSV
        $this->assertCount(50, $users);

        $Beneficiaries = $em->getRepository('App:Beneficiary')->findAll();

        // Assert that the number of beneficiaries in the database matches the number in the CSV
        $this->assertCount(50, $Beneficiaries);

        $memberships = $em->getRepository('App:Membership')->findAll();

        // Assert that the number of memberships in the database matches the number in the CSV
        $this->assertCount(50, $memberships);

    }


    /**
     * @throws Exception
     */
    public function testCsvImportForEmptyBaseWithSemiColons()
    {
        echo "\n\033[32m testCsvImportForEmptyBaseWithSemiColons \033[0m\n";


        $client = static::createClient();

        // Path to the mock CSV file
        $csvPath = __DIR__ . '/../Mocks/mocked_users_semicolon.csv';

        $application = new Application($client->getKernel());
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:import:users',
            '--delimiter' => ';',
            'file' => $csvPath,
            '--default_mapping' => true
        ]);

        $output = new BufferedOutput();
        $application->run($input, $output);

        $content = $output->fetch();

        // Check if the response is successful
        $this->assertTrue(strpos($content, 'Dealing with 50 lines') !== false);

        // Fetch data from the test database and assert
        $em = $client->getContainer()->get('doctrine')->getManager();
        $users = $em->getRepository('App:User')->findAll();

        // Assert that the number of users in the database matches the number in the CSV
        $this->assertCount(50, $users);

        $Beneficiaries = $em->getRepository('App:Beneficiary')->findAll();

        // Assert that the number of beneficiaries in the database matches the number in the CSV
        $this->assertCount(50, $Beneficiaries);

        $memberships = $em->getRepository('App:Membership')->findAll();

        // Assert that the number of memberships in the database matches the number in the CSV
        $this->assertCount(50, $memberships);

    }


    /**
     * @throws Exception
     */
    public function testCsvImportForCommissionFiledBaseWithCommas()
    {

        echo "\n\033[32m testCsvImportForCommissionFiledBaseWithCommas \033[0m\n";

        $this->loadFixturesWithGroups(['commission']);

        $client = static::createClient();

        // Path to the mock CSV file
        $csvPath = __DIR__ . '/../Mocks/mocked_users.csv';

        $application = new Application($client->getKernel());
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:import:users',
            '--delimiter' => ';',
            'file' => $csvPath,
            '--default_mapping' => true
        ]);

        $application->run($input);

        // Fetch data from the test database and assert
        $em = $client->getContainer()->get('doctrine')->getManager();
        $beneficiaries = $em->getRepository('App:Beneficiary')->findAll();

        // Assert that the number of beneficiaries in the database matches the number in the CSV
        $this->assertCount(50, $beneficiaries);

        // count the number of links between beneficiaries and commissions
        $count = 0;
        foreach ($beneficiaries as $beneficiary) {
            if ($beneficiary->getCommissions()->count() > 0) {
                $count+= $beneficiary->getCommissions()->count();
            }
        }

        // Assert that the number of links between beneficiaries and commissions in the database matches the number in the CSV
        $this->assertEquals(67, $count);

    }

    /**
     * @throws Exception
     */
    public function testCsvImportForCommissionFiledBaseWithSemicolons()
    {

        echo "\n\033[32m testCsvImportForCommissionFiledBaseWithSemicolons \033[0m\n";

        $this->loadFixturesWithGroups(['commission']);

        $client = static::createClient();

        // Path to the mock CSV file
        $csvPath = __DIR__ . '/../Mocks/mocked_users_semicolon.csv';

        $application = new Application($client->getKernel());
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:import:users',
            '--delimiter' => ';',
            'file' => $csvPath,
            '--default_mapping' => true
        ]);

        $application->run($input);

        // Fetch data from the test database and assert
        $em = $client->getContainer()->get('doctrine')->getManager();
        $beneficiaries = $em->getRepository('App:Beneficiary')->findAll();

        // Assert that the number of beneficiaries in the database matches the number in the CSV
        $this->assertCount(50, $beneficiaries);

        // count the number of links between beneficiaries and commissions
        $count = 0;
        foreach ($beneficiaries as $beneficiary) {
            if ($beneficiary->getCommissions()->count() > 0) {
                $count+= $beneficiary->getCommissions()->count();
            }
        }

        // Assert that the number of links between beneficiaries and commissions in the database matches the number in the CSV
        $this->assertEquals(67, $count);

    }
}
