<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * User CSV import on an empty database (the purge of setUpBeforeClass()).
 *
 * @internal
 *
 * @coversNothing
 */
class AdminControllerTest extends FunctionalTestCase
{
    /**
     * @dataProvider csvDelimiterProvider
     *
     * @throws \Exception
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
            '--default_mapping' => true,
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

    public static function csvDelimiterProvider(): array
    {
        return [
            'comma-separated' => [__DIR__ . '/../Mocks/mocked_users.csv', ','],
            'semicolon-separated' => [__DIR__ . '/../Mocks/mocked_users_semicolon.csv', ';'],
        ];
    }
}
