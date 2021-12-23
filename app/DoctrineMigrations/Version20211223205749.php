<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211223205749 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE period_position_period');
        $this->addSql('DELETE FROM period_position');
        $this->addSql('ALTER TABLE period DROP week_cycle');
        $this->addSql('ALTER TABLE period_position ADD period_id INT DEFAULT NULL, ADD shifter_id INT DEFAULT NULL, ADD booker_id INT DEFAULT NULL, ADD week_cycle VARCHAR(1) NOT NULL, ADD booked_time DATETIME DEFAULT NULL, DROP nb_of_shifter');
        $this->addSql('ALTER TABLE period_position ADD CONSTRAINT FK_2367D496EC8B7ADE FOREIGN KEY (period_id) REFERENCES period (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE period_position ADD CONSTRAINT FK_2367D496A7DA74C1 FOREIGN KEY (shifter_id) REFERENCES beneficiary (id)');
        $this->addSql('ALTER TABLE period_position ADD CONSTRAINT FK_2367D4968B7E4006 FOREIGN KEY (booker_id) REFERENCES beneficiary (id)');
        $this->addSql('CREATE INDEX IDX_2367D496EC8B7ADE ON period_position (period_id)');
        $this->addSql('CREATE INDEX IDX_2367D496A7DA74C1 ON period_position (shifter_id)');
        $this->addSql('CREATE INDEX IDX_2367D4968B7E4006 ON period_position (booker_id)');
        $this->addSql('ALTER TABLE shift ADD position_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45DD842E46 FOREIGN KEY (position_id) REFERENCES period_position (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A50B3B45DD842E46 ON shift (position_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE period_position_period (period_position_id INT NOT NULL, period_id INT NOT NULL, INDEX IDX_A0A94FFFA95DF5B1 (period_position_id), INDEX IDX_A0A94FFFEC8B7ADE (period_id), PRIMARY KEY(period_position_id, period_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE period_position_period ADD CONSTRAINT FK_A0A94FFFA95DF5B1 FOREIGN KEY (period_position_id) REFERENCES period_position (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE period_position_period ADD CONSTRAINT FK_A0A94FFFEC8B7ADE FOREIGN KEY (period_id) REFERENCES period (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE period ADD week_cycle LONGTEXT CHARACTER SET utf8mb4 DEFAULT \'0,1,2,3\' NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:simple_array)\'');
        $this->addSql('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D496EC8B7ADE');
        $this->addSql('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D496A7DA74C1');
        $this->addSql('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D4968B7E4006');
        $this->addSql('DROP INDEX IDX_2367D496EC8B7ADE ON period_position');
        $this->addSql('DROP INDEX IDX_2367D496A7DA74C1 ON period_position');
        $this->addSql('DROP INDEX IDX_2367D4968B7E4006 ON period_position');
        $this->addSql('ALTER TABLE period_position ADD nb_of_shifter INT NOT NULL, DROP period_id, DROP shifter_id, DROP booker_id, DROP week_cycle, DROP booked_time');
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45DD842E46');
        $this->addSql('DROP INDEX IDX_A50B3B45DD842E46 ON shift');
        $this->addSql('ALTER TABLE shift DROP position_id');
    }
}
