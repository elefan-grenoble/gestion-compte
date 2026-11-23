<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Shifts and period positions can be reserved to new beneficiaries
 * (chosen as "Nouveau" in the same dropdown as the formation).
 */
final class Version20261124100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new_beneficiary_only to shift and period_position';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE shift ADD new_beneficiary_only TINYINT(1) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE period_position ADD new_beneficiary_only TINYINT(1) DEFAULT \'0\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE shift DROP new_beneficiary_only');
        $this->addSql('ALTER TABLE period_position DROP new_beneficiary_only');
    }
}
