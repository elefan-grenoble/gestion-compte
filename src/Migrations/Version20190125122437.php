<?php declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190125122437 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE time_log ADD type SMALLINT NOT NULL');
        $this->addSql("UPDATE time_log SET type = 1 WHERE description = 'Créneau réservé' OR description = 'Créneau réalisé'");
        $this->addSql("UPDATE time_log SET type = 2 WHERE description = 'Début de cycle'");
        $this->addSql("UPDATE time_log SET type = 3 WHERE description = 'Début de cycle (compte gelé)'");
        $this->addSql("UPDATE time_log SET type = 4 WHERE description = 'Début de cycle (adhésion expirée)'");
        $this->addSql("UPDATE time_log SET type = 5 WHERE description = 'Régulation du bénévolat facultatif'");
        $this->addSql('ALTER TABLE time_log CHANGE description description VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE time_log SET description = NULL");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE time_log DROP type');
        $this->addSql('ALTER TABLE time_log CHANGE description description VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
    }
}
