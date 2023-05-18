<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230518142222 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE shiftfreelog DROP FOREIGN KEY FK_6B5F3126BB70BC0E');
        $this->addSql('ALTER TABLE shiftfreelog ADD shift_string LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE shiftfreelog ADD CONSTRAINT FK_6B5F3126BB70BC0E FOREIGN KEY (shift_id) REFERENCES shift (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE shiftfreelog DROP FOREIGN KEY FK_6B5F3126BB70BC0E');
        $this->addSql('ALTER TABLE shiftfreelog DROP shift_string');
        $this->addSql('ALTER TABLE shiftfreelog ADD CONSTRAINT FK_6B5F3126BB70BC0E FOREIGN KEY (shift_id) REFERENCES shift (id) ON DELETE CASCADE');
    }
}
