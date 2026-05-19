<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class AdminControllerTest extends FunctionalTestCase
{
    public function csvDelimiterProvider(): array
    {
        return [
            'comma-separated' => [__DIR__ . '/../Mocks/mocked_users.csv', ','],
            'semicolon-separated' => [__DIR__ . '/../Mocks/mocked_users_semicolon.csv', ';'],
        ];
    }

    /**
     * @dataProvider csvDelimiterProvider
     *
     * @throws Exception
     */
    public function testCsvImportForEmptyBase(string $csvPath, string $delimiter)
    {
        $client = static::createClient();

        $application = new Application($client->getKernel());
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:import:users',
            '--delimiter' => $delimiter,
            'file' => $csvPath,
            '--default_mapping' => true
        ]);

        $output = new BufferedOutput();
        $application->run($input, $output);

        $content = $output->fetch();

        // Check if the response is successful
        $this->assertStringContainsString('Dealing with 50 lines', $content);

        // Fetch data from the test database and assert
        $em = $client->getContainer()->get('doctrine')->getManager();

        $users = $em->getRepository(User::class)->findAll();
        $this->assertCount(50, $users);

        $beneficiaries = $em->getRepository(Beneficiary::class)->findAll();
        $this->assertCount(50, $beneficiaries);

        $memberships = $em->getRepository(Membership::class)->findAll();
        $this->assertCount(50, $memberships);
    }

    /**
     * @dataProvider csvDelimiterProvider
     *
     * @throws Exception
     */
    public function testCsvImportForCommissionFilledBase(string $csvPath, string $delimiter)
    {
        $this->loadFixturesWithGroups(['commission']);

        $client = static::createClient();

        $application = new Application($client->getKernel());
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:import:users',
            '--delimiter' => $delimiter,
            'file' => $csvPath,
            '--default_mapping' => true
        ]);

        $application->run($input);

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
