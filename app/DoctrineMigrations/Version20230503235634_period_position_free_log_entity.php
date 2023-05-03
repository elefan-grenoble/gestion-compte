<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230503235634 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE period_position_free_log (id INT AUTO_INCREMENT NOT NULL, period_position_id INT DEFAULT NULL, beneficiary_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, request_route VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_389B7C61A95DF5B1 (period_position_id), INDEX IDX_389B7C61ECCAAFA0 (beneficiary_id), INDEX IDX_389B7C61B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC');
        $this->addSql('ALTER TABLE period_position_free_log ADD CONSTRAINT FK_389B7C61A95DF5B1 FOREIGN KEY (period_position_id) REFERENCES period_position (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE period_position_free_log ADD CONSTRAINT FK_389B7C61ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE period_position_free_log ADD CONSTRAINT FK_389B7C61B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE period_position_free_log');
    }
}
