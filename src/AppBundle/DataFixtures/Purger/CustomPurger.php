<?php

namespace AppBundle\DataFixtures\Purger;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

class CustomPurger extends ORMPurger
{
    private $entityManager;
    private $excludedTables = ["migration_versions", "dynamic_content"];

    public function __construct(EntityManagerInterface $entityManager, array $excluded = [])
    {
        $this->entityManager = $entityManager;
        parent::__construct($this->entityManager, $excluded);
    }

    /**
     * Purges the MySQL database with temporarily disabled foreign key checks.
     *
     * {@inheritDoc}
     * @throws Exception
     */
    public function purge(): void
    {
        echo "\n Purging database expect tables: " . implode(", ", $this->excludedTables) . "\n";

        $conn = $this->entityManager->getConnection();
        $sm = $conn->getSchemaManager();

        $conn->executeQuery('SET FOREIGN_KEY_CHECKS = 0;');
        foreach ($sm->listTableNames() as $tableName) {
            if (!in_array($tableName, $this->excludedTables, true)) {
                $conn->executeQuery(sprintf('TRUNCATE TABLE %s;', $tableName));
            }
        }
        $conn->executeQuery('SET FOREIGN_KEY_CHECKS = 1;');
    }

}
