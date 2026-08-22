<?php

namespace App\Tests\Functional\Anonymization;

use App\Anonymization\AdminAccountGuarantee;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Its own scratch database, with only the columns AdminAccountGuarantee
 * reads or writes — not a copy of the real fos_user, which
 * AnonymizationLeakTest already covers for the full anonymization pass.
 *
 * @internal
 *
 * @coversNothing
 */
class AdminAccountGuaranteeTest extends KernelTestCase
{
    private const EMAIL_DOMAIN = 'example.invalid';

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
        self::$scratchName = ($parameters['dbname'] ?? 'symfony') . '_admin_guarantee_test_' . getmypid();

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
        self::$scratch->executeStatement('DROP TABLE IF EXISTS fos_user');
        self::$scratch->executeStatement(
            'CREATE TABLE fos_user (
                id INT PRIMARY KEY,
                username VARCHAR(180) UNIQUE,
                username_canonical VARCHAR(180) UNIQUE,
                email VARCHAR(180) UNIQUE,
                email_canonical VARCHAR(180) UNIQUE,
                roles LONGTEXT,
                password VARCHAR(255)
            )'
        );
    }

    public function testRepointsTheLowestIdSuperAdmin(): void
    {
        $this->insert(1, 'zzz_regular', ['ROLE_USER']);
        $this->insert(2, 'first_super_admin', ['ROLE_SUPER_ADMIN']);
        $this->insert(3, 'second_super_admin', ['ROLE_SUPER_ADMIN']);

        (new AdminAccountGuarantee(self::$scratch))->apply(self::EMAIL_DOMAIN);

        $row = self::$scratch->fetchAssociative('SELECT username, email FROM fos_user WHERE id = 2');
        $this->assertSame(AdminAccountGuarantee::USERNAME, $row['username']);
        $this->assertSame('admin@' . self::EMAIL_DOMAIN, $row['email']);

        // Only the lowest-id super admin moves — a second row keeping its
        // anonymized name is the proof only one account was touched.
        $untouched = self::$scratch->fetchAssociative('SELECT username FROM fos_user WHERE id = 3');
        $this->assertSame('second_super_admin', $untouched['username']);
    }

    public function testLeavesNonAdminAccountsAlone(): void
    {
        $this->insert(1, 'zzz_regular', ['ROLE_USER']);
        $this->insert(2, 'the_admin', ['ROLE_SUPER_ADMIN']);

        (new AdminAccountGuarantee(self::$scratch))->apply(self::EMAIL_DOMAIN);

        $regular = self::$scratch->fetchAssociative('SELECT username FROM fos_user WHERE id = 1');
        $this->assertSame('zzz_regular', $regular['username']);
    }

    /**
     * A second run — the shape of re-exporting the same source, or of a
     * future caller applying it twice by mistake — must not trip the
     * UNIQUE constraint on username/email by trying to move a second row
     * onto a name the first run already claimed.
     */
    public function testIsIdempotent(): void
    {
        $this->insert(1, 'first_admin', ['ROLE_SUPER_ADMIN']);

        $guarantee = new AdminAccountGuarantee(self::$scratch);
        $guarantee->apply(self::EMAIL_DOMAIN);
        $guarantee->apply(self::EMAIL_DOMAIN);

        $row = self::$scratch->fetchAssociative('SELECT username, email FROM fos_user WHERE id = 1');
        $this->assertSame(AdminAccountGuarantee::USERNAME, $row['username']);
        $this->assertSame('admin@' . self::EMAIL_DOMAIN, $row['email']);
    }

    public function testRefusesWhenNoSuperAdminExists(): void
    {
        $this->insert(1, 'zzz_regular', ['ROLE_USER']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ROLE_SUPER_ADMIN/');

        (new AdminAccountGuarantee(self::$scratch))->apply(self::EMAIL_DOMAIN);
    }

    /**
     * @param string[] $roles
     */
    private function insert(int $id, string $username, array $roles): void
    {
        self::$scratch->executeStatement(
            'INSERT INTO fos_user (id, username, username_canonical, email, email_canonical, roles, password)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $username, $username,
                $username . '@source.example', $username . '@source.example',
                serialize($roles), 'irrelevant',
            ]
        );
    }
}
