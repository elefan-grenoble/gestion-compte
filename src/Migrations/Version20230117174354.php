<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230117174354 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE shiftfreelog (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, shift_id INT DEFAULT NULL, beneficiary_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', reason VARCHAR(255) DEFAULT NULL, request_route VARCHAR(255) DEFAULT NULL, INDEX IDX_6B5F3126B03A8386 (created_by_id), INDEX IDX_6B5F3126BB70BC0E (shift_id), INDEX IDX_6B5F3126ECCAAFA0 (beneficiary_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC');
        $this->addSql('ALTER TABLE shiftfreelog ADD CONSTRAINT FK_6B5F3126B03A8386 FOREIGN KEY (created_by_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE shiftfreelog ADD CONSTRAINT FK_6B5F3126BB70BC0E FOREIGN KEY (shift_id) REFERENCES shift (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shiftfreelog ADD CONSTRAINT FK_6B5F3126ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE shiftfreelog');
    }
}
