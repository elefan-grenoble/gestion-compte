<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221102184021 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE membership ADD withdrawn_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE membership ADD withdrawn_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD28589BE4D2E FOREIGN KEY (withdrawn_by_id) REFERENCES fos_user (id)');
        $this->addSql('CREATE INDEX IDX_86FFD28589BE4D2E ON membership (withdrawn_by_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE membership DROP withdrawn_date');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD28589BE4D2E');
        $this->addSql('DROP INDEX IDX_86FFD28589BE4D2E ON membership');
        $this->addSql('ALTER TABLE membership DROP withdrawn_by_id');
    }
}
