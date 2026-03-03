<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230417140110 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE event ADD event_kind_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7B5378506 FOREIGN KEY (event_kind_id) REFERENCES event_kind (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7B5378506 ON event (event_kind_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7B5378506');
        $this->addSql('DROP INDEX IDX_3BAE0AA7B5378506 ON event');
        $this->addSql('ALTER TABLE event DROP event_kind_id');
    }
}
