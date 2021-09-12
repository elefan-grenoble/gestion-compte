<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210912120800 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE event CHANGE min_date_of_last_registration max_date_of_last_registration DATETIME DEFAULT NULL');
        $this->addSql('UPDATE event SET max_date_of_last_registration = DATE_ADD(max_date_of_last_registration, INTERVAL 1 YEAR) WHERE max_date_of_last_registration != NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE event CHANGE max_date_of_last_registration min_date_of_last_registration DATETIME DEFAULT NULL');
        $this->addSql('UPDATE event SET min_date_of_last_registration = DATE_SUB(min_date_of_last_registration, INTERVAL 1 YEAR) WHERE min_date_of_last_registration != NULL');
    }
}
