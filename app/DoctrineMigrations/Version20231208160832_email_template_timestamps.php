<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231208160832 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE email_template ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C0600CAB03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C0600CA896DBBDE FOREIGN KEY (updated_by_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY FK_9C0600CAB03A8386');
        $this->addSql('ALTER TABLE email_template DROP FOREIGN KEY FK_9C0600CA896DBBDE');
    }
}
