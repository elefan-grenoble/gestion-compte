<?php declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Some entites we had in the past but forgot to remove them
 */
final class Version20181111111529_drop_unmapped_tables extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        if ($schema->hasTable('beneficiary_task')) {
            $this->addSql('DROP TABLE beneficiary_task');
        }
        if ($schema->hasTable('helloasso_notification')) {
            $this->addSql('DROP TABLE helloasso_notification');
        }
        if ($schema->hasTable('users_commissions')) {
            $this->addSql('DROP TABLE users_commissions');
        }
        if ($schema->hasTable('users_services')) {
            $this->addSql('DROP TABLE users_services');
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE beneficiary_task (beneficiary_id INT NOT NULL, task_id INT NOT NULL, INDEX IDX_D37D23E9ECCAAFA0 (beneficiary_id), INDEX IDX_D37D23E98DB60186 (task_id), PRIMARY KEY(beneficiary_id, task_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE helloasso_notification (id INT AUTO_INCREMENT NOT NULL, registration_id INT DEFAULT NULL, created_at DATETIME NOT NULL, notification_id INT NOT NULL, date DATETIME NOT NULL, amount DOUBLE PRECISION NOT NULL, url VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, payer_first_name VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, payer_last_name VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, url_receipt VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, url_tax_receipt VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, content LONGTEXT NOT NULL COLLATE utf8_unicode_ci, UNIQUE INDEX UNIQ_FE8CA030EF1A9D84 (notification_id), UNIQUE INDEX UNIQ_FE8CA030833D8F43 (registration_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users_commissions (user_id INT NOT NULL, commission_id INT NOT NULL, INDEX IDX_89174F3EA76ED395 (user_id), INDEX IDX_89174F3E202D1EB2 (commission_id), PRIMARY KEY(user_id, commission_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users_services (user_id INT NOT NULL, service_id INT NOT NULL, INDEX IDX_873CAB3A76ED395 (user_id), INDEX IDX_873CAB3ED5CA9E6 (service_id), PRIMARY KEY(user_id, service_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE beneficiary_task ADD CONSTRAINT FK_D37D23E98DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beneficiary_task ADD CONSTRAINT FK_D37D23E9ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE helloasso_notification ADD CONSTRAINT FK_FE8CA030833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users_commissions ADD CONSTRAINT FK_89174F3E202D1EB2 FOREIGN KEY (commission_id) REFERENCES commission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users_commissions ADD CONSTRAINT FK_89174F3EA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users_services ADD CONSTRAINT FK_873CAB3A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users_services ADD CONSTRAINT FK_873CAB3ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
    }
}
