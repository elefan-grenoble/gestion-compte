<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230825120912 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE opening_hour ADD opening_hour_kind_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE opening_hour ADD CONSTRAINT FK_969BD7659BA06AF0 FOREIGN KEY (opening_hour_kind_id) REFERENCES opening_hour_kind (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_969BD7659BA06AF0 ON opening_hour (opening_hour_kind_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE opening_hour DROP FOREIGN KEY FK_969BD7659BA06AF0');
        $this->addSql('DROP INDEX IDX_969BD7659BA06AF0 ON opening_hour');
        $this->addSql('ALTER TABLE opening_hour DROP opening_hour_kind_id');
    }
}
