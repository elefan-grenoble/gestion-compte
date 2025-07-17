<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221029121504 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE membership_shift_exemption (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, shift_exemption_id INT DEFAULT NULL, membership_id INT NOT NULL, created_at DATETIME NOT NULL, description VARCHAR(255) NOT NULL, start DATE NOT NULL, end DATE NOT NULL, INDEX IDX_BA11DB4CB03A8386 (created_by_id), INDEX IDX_BA11DB4C24221478 (shift_exemption_id), INDEX IDX_BA11DB4C1FB354CD (membership_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC');
        $this->addSql('ALTER TABLE membership_shift_exemption ADD CONSTRAINT FK_BA11DB4CB03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE membership_shift_exemption ADD CONSTRAINT FK_BA11DB4C24221478 FOREIGN KEY (shift_exemption_id) REFERENCES shift_exemption (id)');
        $this->addSql('ALTER TABLE membership_shift_exemption ADD CONSTRAINT FK_BA11DB4C1FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE membership_shift_exemption');
    }
}
