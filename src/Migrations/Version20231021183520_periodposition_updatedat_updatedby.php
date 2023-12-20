<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231021183520 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE period_position ADD updated_by_id INT DEFAULT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE period_position ADD CONSTRAINT FK_2367D496896DBBDE FOREIGN KEY (updated_by_id) REFERENCES fos_user (id)');
        $this->addSql('CREATE INDEX IDX_2367D496896DBBDE ON period_position (updated_by_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D496896DBBDE');
        $this->addSql('DROP INDEX IDX_2367D496896DBBDE ON period_position');
        $this->addSql('ALTER TABLE period_position DROP updated_by_id, DROP updated_at');
    }
}
