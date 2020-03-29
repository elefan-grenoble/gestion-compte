<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200125080621 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD2856986CF73');
        $this->addSql('DROP INDEX UNIQ_86FFD2856986CF73 ON membership');
        $this->addSql('ALTER TABLE membership DROP last_registration_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE membership ADD last_registration_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD2856986CF73 FOREIGN KEY (last_registration_id) REFERENCES registration (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_86FFD2856986CF73 ON membership (last_registration_id)');
    }
}
