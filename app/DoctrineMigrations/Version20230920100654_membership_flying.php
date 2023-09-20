<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230920100654 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE membership ADD flying TINYINT(1) DEFAULT \'0\' NOT NULL');
        $this->addSql('UPDATE membership LEFT OUTER JOIN beneficiary ON beneficiary.id = membership.main_beneficiary_id SET membership.flying=beneficiary.flying WHERE beneficiary.flying = 1');
        $this->addSql('ALTER TABLE beneficiary DROP flying');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE beneficiary ADD flying TINYINT(1) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE membership DROP flying');
    }
}
