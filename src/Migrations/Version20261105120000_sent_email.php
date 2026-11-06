<?php

declare(strict_types=1);

namespace app\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * History of the sent mails.
 */
final class Version20261105120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sent_email (history of the sent mails)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE sent_email (id INT AUTO_INCREMENT NOT NULL, sent_by_id INT DEFAULT NULL, created_at DATETIME NOT NULL, source VARCHAR(16) NOT NULL, from_address VARCHAR(255) NOT NULL, recipients LONGTEXT NOT NULL, recipient_count INT NOT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT DEFAULT NULL, status VARCHAR(16) NOT NULL, error LONGTEXT DEFAULT NULL, message_id VARCHAR(255) DEFAULT NULL, batch_id VARCHAR(36) DEFAULT NULL, INDEX IDX_E92EE5FCA45BB98C (sent_by_id), INDEX sent_email_created_at_idx (created_at), INDEX sent_email_status_idx (status), INDEX sent_email_message_id_idx (message_id), INDEX sent_email_batch_id_idx (batch_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sent_email ADD CONSTRAINT FK_E92EE5FCA45BB98C FOREIGN KEY (sent_by_id) REFERENCES fos_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE sent_email DROP FOREIGN KEY FK_E92EE5FCA45BB98C');
        $this->addSql('DROP TABLE sent_email');
    }
}
