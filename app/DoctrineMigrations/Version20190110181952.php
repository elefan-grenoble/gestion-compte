<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190110181952 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE abstract_registration (id VARCHAR(255) NOT NULL, registrar_id INT DEFAULT NULL, membership_id INT DEFAULT NULL, type INT NOT NULL, date DATETIME NOT NULL, amount VARCHAR(255) NOT NULL, mode INT NOT NULL, beneficiary VARCHAR(255) NOT NULL, INDEX IDX_F1C31E96D1AA2FC1 (registrar_id), INDEX IDX_F1C31E961FB354CD (membership_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cms_dynamic_content (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE abstract_registration ADD CONSTRAINT FK_F1C31E96D1AA2FC1 FOREIGN KEY (registrar_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE abstract_registration ADD CONSTRAINT FK_F1C31E961FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE abstract_registration');
        $this->addSql('DROP TABLE cms_dynamic_content');
    }
}
