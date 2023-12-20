<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230403195605 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE shift ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
        $this->addSql('CREATE INDEX IDX_A50B3B45B03A8386 ON shift (created_by_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45B03A8386');
        $this->addSql('DROP INDEX IDX_A50B3B45B03A8386 ON shift');
        $this->addSql('ALTER TABLE shift DROP created_by_id');
    }
}
