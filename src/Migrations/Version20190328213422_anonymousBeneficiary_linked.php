<?php declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190328213422_anonymousBeneficiary_linked extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE anonymous_beneficiary ADD join_to INT DEFAULT NULL, CHANGE amount amount VARCHAR(255) DEFAULT NULL, CHANGE mode mode INT DEFAULT NULL');
        $this->addSql('ALTER TABLE anonymous_beneficiary ADD CONSTRAINT FK_4864BDFAF56D4DE0 FOREIGN KEY (join_to) REFERENCES beneficiary (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4864BDFAF56D4DE0 ON anonymous_beneficiary (join_to)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE anonymous_beneficiary DROP FOREIGN KEY FK_4864BDFAF56D4DE0');
        $this->addSql('DROP INDEX UNIQ_4864BDFAF56D4DE0 ON anonymous_beneficiary');
        $this->addSql('ALTER TABLE anonymous_beneficiary DROP join_to, CHANGE amount amount VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE mode mode INT NOT NULL');
    }
}
