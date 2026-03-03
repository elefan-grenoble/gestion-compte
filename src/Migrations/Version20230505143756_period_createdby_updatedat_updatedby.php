<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230505143756 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE period ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, ADD updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECEB03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECE896DBBDE FOREIGN KEY (updated_by_id) REFERENCES fos_user (id)');
        $this->addSql('CREATE INDEX IDX_C5B81ECEB03A8386 ON period (created_by_id)');
        $this->addSql('CREATE INDEX IDX_C5B81ECE896DBBDE ON period (updated_by_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEB03A8386');
        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECE896DBBDE');
        $this->addSql('DROP INDEX IDX_C5B81ECEB03A8386 ON period');
        $this->addSql('DROP INDEX IDX_C5B81ECE896DBBDE ON period');
        $this->addSql('ALTER TABLE period DROP created_by_id, DROP updated_by_id, DROP updated_at');
    }
}
