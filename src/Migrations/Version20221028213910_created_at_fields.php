<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221028213910 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE commission ADD created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE event ADD created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE formation ADD created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE job ADD created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE shift ADD created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE commission DROP created_at');
        $this->addSql('ALTER TABLE event DROP created_at');
        $this->addSql('ALTER TABLE formation DROP created_at');
        $this->addSql('ALTER TABLE job DROP created_at');
        $this->addSql('ALTER TABLE shift DROP created_at');
    }
}
