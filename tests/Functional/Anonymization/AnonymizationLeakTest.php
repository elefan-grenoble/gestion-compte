<?php

namespace App\Tests\Functional\Anonymization;

use App\Anonymization\Anonymizer;
use App\Anonymization\LeakScanner;
use App\Anonymization\Manifest;
use App\Anonymization\RuleRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Entity\User;

/**
 * Exercises the anonymizer end to end against known personal data.
 *
 * The real guarantee lives in the export pipeline, which refuses to
 * deliver a dump that does not pass the same LeakScanner. This test is
 * the early-warning copy of that check: it fails in CI, at review time,
 * rather than at export time in front of whoever needed the dump.
 *
 * It builds its own scratch database — structure copied from the
 * configured one, rows written by hand — so it neither depends on nor
 * disturbs whatever the developer happens to have locally. Same reason
 * the export never anonymizes in place.
 *
 * @internal
 *
 * @coversNothing
 */
class AnonymizationLeakTest extends KernelTestCase
{
    private const CANARIES = [
        'lastname' => 'Kowalczyk-Canary',
        'firstname' => 'Bartholomew',
        'email' => 'bartholomew.kowalczyk@realmail.example',
        'phone' => '0612345678',
        'street' => '42 impasse du Canari',
        'city' => 'Villeurbanne',
        'note' => 'echange telephonique avec Kowalczyk-Canary au 0612345678',
        'token' => 'confirmation-canary-2f8a1c',
        'password' => '$2y$13$Zx1Kowalczyk000000000uOa3XwOZ1sPqLb7cVn2mEyTgQhJrKdWu',
    ];

    /** @var Connection */
    private static $source;

    /** @var Connection */
    private static $scratch;

    /** @var string */
    private static $scratchName;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$source = self::$container->get('doctrine.dbal.default_connection');

        $parameters = self::$source->getParams();
        self::$scratchName = ($parameters['dbname'] ?? 'symfony') . '_anon_test_' . getmypid();

        self::$source->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', self::$scratchName));
        self::$source->executeStatement(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4', self::$scratchName));

        $parameters['dbname'] = self::$scratchName;
        unset($parameters['url']);
        self::$scratch = DriverManager::getConnection($parameters);
    }

    public static function tearDownAfterClass(): void
    {
        if (null !== self::$scratch) {
            self::$scratch->close();
        }
        if (null !== self::$source && null !== self::$scratchName) {
            self::$source->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', self::$scratchName));
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        $this->copyStructure();
        $this->plantCanaries();

        (new Anonymizer(self::$scratch, $this->manifest(), new RuleRegistry()))->run();
    }

    public function testNoPlantedPersonalDataSurvivesAnywhere(): void
    {
        $scanner = new LeakScanner(array_values(self::CANARIES));
        $findings = [];

        foreach ($this->textColumnsByTable() as $table => $columns) {
            foreach (self::$scratch->fetchAllAssociative(sprintf('SELECT * FROM `%s`', $table)) as $index => $row) {
                foreach ($columns as $column) {
                    if (null === $row[$column] || '' === $row[$column]) {
                        continue;
                    }

                    $findings = array_merge(
                        $findings,
                        $scanner->scanText(sprintf('%s.%s (row %d)', $table, $column, $index + 1), (string) $row[$column])
                    );
                }
            }
        }

        $this->assertSame([], $findings, sprintf(
            "Personal data survived anonymization:\n  - %s",
            implode("\n  - ", $findings)
        ));
    }

    public function testEveryAccountEndsUpWithTheSamePublishedPassword(): void
    {
        $hashes = self::$scratch->fetchFirstColumn('SELECT DISTINCT password FROM fos_user');

        $this->assertCount(1, $hashes, 'Every account is meant to share one password, so one hash should cover them all.');
        $this->assertTrue(
            password_verify(RuleRegistry::DEFAULT_PASSWORD, $hashes[0]),
            sprintf('The stored hash should verify against "%s".', RuleRegistry::DEFAULT_PASSWORD)
        );
    }

    /**
     * The password is chosen per run, so the export and whoever verifies
     * it have to be told the same thing.
     */
    public function testThePasswordCanBeChosenPerRun(): void
    {
        (new Anonymizer(self::$scratch, $this->manifest(), new RuleRegistry('S0me-Other-Choice')))->run();

        $hashes = self::$scratch->fetchFirstColumn('SELECT DISTINCT password FROM fos_user');

        $this->assertCount(1, $hashes);
        $this->assertTrue(password_verify('S0me-Other-Choice', $hashes[0]));
        $this->assertFalse(password_verify(RuleRegistry::DEFAULT_PASSWORD, $hashes[0]));
    }

    public function testCredentialResidueIsCleared(): void
    {
        $residue = (int) self::$scratch->fetchOne(
            'SELECT COUNT(*) FROM fos_user WHERE salt IS NOT NULL OR confirmation_token IS NOT NULL OR password_requested_at IS NOT NULL'
        );

        $this->assertSame(0, $residue, 'Password reset tokens must not survive: they are enough to take an account over.');
    }

    public function testEveryEmailIsUndeliverable(): void
    {
        $foreign = self::$scratch->fetchFirstColumn(
            "SELECT email FROM fos_user WHERE email NOT LIKE '%@" . RuleRegistry::EMAIL_DOMAIN . "'"
        );

        $this->assertSame([], $foreign, 'A developer instance pointed at a real SMTP server must not be able to reach a real person.');
    }

    public function testTruncatedTablesAreEmpty(): void
    {
        foreach ($this->manifest()->tablesWithStrategy(Manifest::STRATEGY_TRUNCATE) as $table) {
            $this->assertSame(
                0,
                (int) self::$scratch->fetchOne(sprintf('SELECT COUNT(*) FROM `%s`', $table)),
                sprintf('%s is declared as truncated but still holds rows.', $table)
            );
        }
    }

    /**
     * The seed mechanism exists so a user and their beneficiary keep
     * telling the same story; without it the export is confusing to work
     * with even though it is technically anonymous.
     */
    public function testUserAndBeneficiaryShareOneIdentity(): void
    {
        $row = self::$scratch->fetchAssociative(
            'SELECT u.username, b.firstname, b.lastname FROM fos_user u INNER JOIN beneficiary b ON b.user_id = u.id LIMIT 1'
        );

        $this->assertNotFalse($row);
        $this->assertStringStartsWith(
            User::makeUsername($row['firstname'], $row['lastname']),
            $row['username']
        );
    }

    private function manifest(): Manifest
    {
        return Manifest::fromFile(Manifest::defaultPath(), (new RuleRegistry())->names());
    }

    /**
     * Structure only. Views are skipped: CREATE TABLE LIKE cannot copy
     * one, and the manifest already treats them as projections of tables
     * that do get anonymized.
     */
    private function copyStructure(): void
    {
        $source = self::$source->getParams()['dbname'];
        $manifest = $this->manifest();

        $tables = self::$source->fetchFirstColumn(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
        );

        self::$scratch->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            if ($manifest->has($table) && Manifest::STRATEGY_VIEW === $manifest->strategy($table)) {
                continue;
            }

            self::$scratch->executeStatement(sprintf('DROP TABLE IF EXISTS `%s`', $table));
            self::$scratch->executeStatement(sprintf('CREATE TABLE `%s` LIKE `%s`.`%s`', $table, $source, $table));
        }
        self::$scratch->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Written straight through SQL rather than through the entities: the
     * point is to simulate rows that are already in a database, including
     * shapes the ORM would not produce.
     */
    private function plantCanaries(): void
    {
        self::$scratch->executeStatement(
            'INSERT INTO fos_user (id, username, username_canonical, email, email_canonical, enabled, password, confirmation_token, roles)
             VALUES (1, ?, ?, ?, ?, 1, ?, ?, ?)',
            [
                'bkowalczyk', 'bkowalczyk',
                self::CANARIES['email'], self::CANARIES['email'],
                self::CANARIES['password'], self::CANARIES['token'],
                serialize([]),
            ]
        );

        self::$scratch->executeStatement(
            'INSERT INTO address (id, street1, street2, zipcode, city) VALUES (1, ?, ?, ?, ?)',
            [self::CANARIES['street'], '', '69100', self::CANARIES['city']]
        );

        self::$scratch->executeStatement(
            'INSERT INTO beneficiary (id, user_id, address_id, lastname, firstname, phone, flying, created_at)
             VALUES (1, 1, 1, ?, ?, ?, 0, NOW())',
            [self::CANARIES['lastname'], self::CANARIES['firstname'], self::CANARIES['phone']]
        );

        self::$scratch->executeStatement(
            'INSERT INTO time_log (id, membership_id, created_at, time, type, description) VALUES (1, 1, NOW(), 0, 0, ?)',
            [self::CANARIES['note']]
        );

        // A row in a table the manifest truncates, to prove truncation is
        // actually reached rather than merely declared.
        self::$scratch->executeStatement(
            'INSERT INTO note (id, membership_id, text, created_at) VALUES (1, 1, ?, NOW())',
            [self::CANARIES['note']]
        );
    }

    /**
     * @return array<string, string[]>
     */
    private function textColumnsByTable(): array
    {
        $rows = self::$scratch->fetchAllAssociative(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND DATA_TYPE IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext')
             ORDER BY TABLE_NAME, ORDINAL_POSITION"
        );

        $byTable = [];
        foreach ($rows as $row) {
            $byTable[$row['t']][] = $row['c'];
        }

        return $byTable;
    }
}
