<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210224215308 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE swipe_card_log DROP FOREIGN KEY FK_EB23E311C81A7349');
        $this->addSql('DROP INDEX IDX_EB23E311C81A7349 ON swipe_card_log');
        $this->addSql('ALTER TABLE swipe_card_log DROP COLUMN swipe_card_id');
        $this->addSql('ALTER TABLE swipe_card_log ADD counter INT NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE swipe_card_log DROP COLUMN counter');
        $this->addSql('ALTER TABLE swipe_card_log ADD swipe_card_id INT NOT NULL');
        $this->addSql('ALTER TABLE swipe_card_log ADD CONSTRAINT FK_EB23E311C81A7349 FOREIGN KEY (swipe_card_id) REFERENCES swipe_card (id)');
        $this->addSql('CREATE INDEX IDX_EB23E311C81A7349 ON swipe_card_log (swipe_card_id)');
    }
}
