<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221120184935 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE membership_log (id INT AUTO_INCREMENT NOT NULL, membership_id INT NOT NULL, created_by_id INT DEFAULT NULL, type VARCHAR(64) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_1210D0F1FB354CD (membership_id), INDEX IDX_1210D0FB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC');
        $this->addSql('ALTER TABLE membership_log ADD CONSTRAINT FK_1210D0F1FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership_log ADD CONSTRAINT FK_1210D0FB03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE membership_log');
    }
}
