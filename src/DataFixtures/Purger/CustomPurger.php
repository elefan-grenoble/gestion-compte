<?php

namespace App\DataFixtures\Purger;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

class CustomPurger extends ORMPurger
{
    private $entityManager;
    private $excludedTables = ['migration_versions', 'dynamic_content'];

    public function __construct(EntityManagerInterface $entityManager, array $excluded = [])
    {
        $this->entityManager = $entityManager;
        parent::__construct($this->entityManager, $excluded);
    }

    /**
     * Purges the MySQL database with temporarily disabled foreign key checks.
     *
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function purge(): void
    {
        echo "\n Purging database expect tables: " . implode(', ', $this->excludedTables) . "\n";

        $conn = $this->entityManager->getConnection();
        $sm = $conn->getSchemaManager();

        $conn->executeQuery('SET FOREIGN_KEY_CHECKS = 0;');
        foreach ($sm->listTableNames() as $tableName) {
            if (!in_array($tableName, $this->excludedTables, true)) {
                $conn->executeQuery(sprintf('TRUNCATE TABLE %s;', $tableName));
            }
        }
        $conn->executeQuery('SET FOREIGN_KEY_CHECKS = 1;');

        $this->reopenTransactionClosedByTruncate($conn);
    }

    /**
     * The fixtures executor purges inside the transaction it opens around the
     * whole load, and TRUNCATE commits it implicitly. DBAL still counts it as
     * open, so its final commit() reaches PDO with nothing to commit, which
     * PHP 8 reports as "There is no active transaction" (PHP 7.4 only tracked
     * its own flag and let it pass). Reopening it keeps the fixtures load in
     * one transaction, as the executor intends.
     */
    private function reopenTransactionClosedByTruncate(Connection $conn): void
    {
        if (!$conn->isTransactionActive()) {
            return;
        }

        $driverConnection = $conn->getWrappedConnection();
        if ($driverConnection instanceof \PDO && !$driverConnection->inTransaction()) {
            $driverConnection->beginTransaction();
        }
    }
}
