<?php declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181103153303_initial extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->abortIf(
            $schema->hasTable('fos_user') && !$schema->hasTable('membership'),
            'Some tables appear to be missing from your database, you have to migrate manually your database.'
        );

        $this->skipIf($schema->hasTable('fos_user'), 'Good ! The database seems to be already populated, skipping this migration...');

        $this->addSql('CREATE TABLE access_token (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, user_id INT DEFAULT NULL, token VARCHAR(255) NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_B6A2DD685F37A13B (token), INDEX IDX_B6A2DD6819EB6921 (client_id), INDEX IDX_B6A2DD68A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, service_id INT DEFAULT NULL, random_id VARCHAR(255) NOT NULL, redirect_uris LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', secret VARCHAR(255) NOT NULL, allowed_grant_types LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', INDEX IDX_C7440455ED5CA9E6 (service_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fos_user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, username_canonical VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, email_canonical VARCHAR(180) NOT NULL, enabled TINYINT(1) NOT NULL, salt VARCHAR(255) DEFAULT NULL, password VARCHAR(255) NOT NULL, last_login DATETIME DEFAULT NULL, confirmation_token VARCHAR(180) DEFAULT NULL, password_requested_at DATETIME DEFAULT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', UNIQUE INDEX UNIQ_957A647992FC23A8 (username_canonical), UNIQUE INDEX UNIQ_957A6479A0D96FBF (email_canonical), UNIQUE INDEX UNIQ_957A6479C05FB297 (confirmation_token), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users_clients (user_id INT NOT NULL, client_id INT NOT NULL, INDEX IDX_F0C85ABEA76ED395 (user_id), INDEX IDX_F0C85ABE19EB6921 (client_id), PRIMARY KEY(user_id, client_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE refresh_token (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, user_id INT DEFAULT NULL, token VARCHAR(255) NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_C74F21955F37A13B (token), INDEX IDX_C74F219519EB6921 (client_id), INDEX IDX_C74F2195A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, icon VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, public TINYINT(1) DEFAULT \'0\', url VARCHAR(255) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, logo_size INT DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE anonymous_beneficiary (id INT AUTO_INCREMENT NOT NULL, registrar_id INT DEFAULT NULL, email VARCHAR(255) NOT NULL, amount VARCHAR(255) NOT NULL, mode INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_4864BDFAE7927C74 (email), INDEX IDX_4864BDFAD1AA2FC1 (registrar_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE note (id INT AUTO_INCREMENT NOT NULL, author_id INT DEFAULT NULL, membership_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, text LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_CFBDFA14F675F31B (author_id), INDEX IDX_CFBDFA141FB354CD (membership_id), INDEX IDX_CFBDFA14727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE time_log (id INT AUTO_INCREMENT NOT NULL, membership_id INT NOT NULL, shift_id INT DEFAULT NULL, date DATETIME NOT NULL, time SMALLINT NOT NULL, description VARCHAR(255) NOT NULL, INDEX IDX_55BE03AF1FB354CD (membership_id), INDEX IDX_55BE03AFBB70BC0E (shift_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE code (id INT AUTO_INCREMENT NOT NULL, registrar_id INT DEFAULT NULL, value VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, closed TINYINT(1) DEFAULT \'0\' NOT NULL, INDEX IDX_77153098D1AA2FC1 (registrar_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE auth_code (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, user_id INT DEFAULT NULL, token VARCHAR(255) NOT NULL, redirect_uri LONGTEXT NOT NULL, expires_at INT DEFAULT NULL, scope VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_5933D02C5F37A13B (token), INDEX IDX_5933D02C19EB6921 (client_id), INDEX IDX_5933D02CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE period_position (id INT AUTO_INCREMENT NOT NULL, formation_id INT DEFAULT NULL, nb_of_shifter INT NOT NULL, INDEX IDX_2367D4965200282E (formation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE period_position_period (period_position_id INT NOT NULL, period_id INT NOT NULL, INDEX IDX_A0A94FFFA95DF5B1 (period_position_id), INDEX IDX_A0A94FFFEC8B7ADE (period_id), PRIMARY KEY(period_position_id, period_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE address (id INT AUTO_INCREMENT NOT NULL, street1 VARCHAR(255) NOT NULL, street2 VARCHAR(255) DEFAULT NULL, zipcode VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, updated_at DATETIME NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, img VARCHAR(255) DEFAULT NULL, img_size INT DEFAULT NULL, date DATETIME NOT NULL, min_date_of_last_registration DATETIME DEFAULT NULL, need_proxy TINYINT(1) DEFAULT \'0\', PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shift (id INT AUTO_INCREMENT NOT NULL, shifter_id INT DEFAULT NULL, booker_id INT DEFAULT NULL, last_shifter_id INT DEFAULT NULL, formation_id INT DEFAULT NULL, job_id INT DEFAULT NULL, start DATETIME NOT NULL, end DATETIME NOT NULL, booked_time DATETIME DEFAULT NULL, is_dismissed TINYINT(1) DEFAULT \'0\' NOT NULL, dismissed_time DATETIME DEFAULT NULL, dismissed_reason VARCHAR(255) DEFAULT NULL, INDEX IDX_A50B3B45A7DA74C1 (shifter_id), INDEX IDX_A50B3B458B7E4006 (booker_id), INDEX IDX_A50B3B454EAE1E2B (last_shifter_id), INDEX IDX_A50B3B455200282E (formation_id), INDEX IDX_A50B3B45BE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE period (id INT AUTO_INCREMENT NOT NULL, job_id INT DEFAULT NULL, day_of_week SMALLINT NOT NULL, start TIME NOT NULL, end TIME NOT NULL, INDEX IDX_C5B81ECEBE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE job (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, color VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_FBD8E0F85E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE task (id INT AUTO_INCREMENT NOT NULL, registrar_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, due_date DATE DEFAULT NULL, closed TINYINT(1) DEFAULT \'0\', priority SMALLINT NOT NULL, status VARCHAR(255) DEFAULT NULL, INDEX IDX_527EDB25D1AA2FC1 (registrar_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tasks_commissions (task_id INT NOT NULL, commission_id INT NOT NULL, INDEX IDX_C14A17118DB60186 (task_id), INDEX IDX_C14A1711202D1EB2 (commission_id), PRIMARY KEY(task_id, commission_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tasks_beneficiaries (task_id INT NOT NULL, beneficiary_id INT NOT NULL, INDEX IDX_1C93D30B8DB60186 (task_id), INDEX IDX_1C93D30BECCAAFA0 (beneficiary_id), PRIMARY KEY(task_id, beneficiary_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE registration (id INT AUTO_INCREMENT NOT NULL, membership_id INT DEFAULT NULL, registrar_id INT DEFAULT NULL, date DATETIME NOT NULL, created_at DATETIME NOT NULL, amount VARCHAR(255) NOT NULL, mode INT NOT NULL, INDEX IDX_62A8A7A71FB354CD (membership_id), INDEX IDX_62A8A7A7D1AA2FC1 (registrar_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE beneficiary (id INT AUTO_INCREMENT NOT NULL, address_id INT DEFAULT NULL, user_id INT NOT NULL, membership_id INT DEFAULT NULL, commission_id INT DEFAULT NULL, lastname VARCHAR(255) NOT NULL, firstname VARCHAR(255) NOT NULL, phone VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_7ABF446AF5B7AF75 (address_id), UNIQUE INDEX UNIQ_7ABF446AA76ED395 (user_id), INDEX IDX_7ABF446A1FB354CD (membership_id), INDEX IDX_7ABF446A202D1EB2 (commission_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE beneficiaries_commissions (beneficiary_id INT NOT NULL, commission_id INT NOT NULL, INDEX IDX_F87A72B6ECCAAFA0 (beneficiary_id), INDEX IDX_F87A72B6202D1EB2 (commission_id), PRIMARY KEY(beneficiary_id, commission_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE beneficiaries_formations (beneficiary_id INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_4B438FE7ECCAAFA0 (beneficiary_id), INDEX IDX_4B438FE75200282E (formation_id), PRIMARY KEY(beneficiary_id, formation_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE helloasso_payment (id INT AUTO_INCREMENT NOT NULL, registration_id INT DEFAULT NULL, created_at DATETIME NOT NULL, payment_id INT NOT NULL, campaign_id INT DEFAULT NULL, date DATETIME NOT NULL, amount DOUBLE PRECISION NOT NULL, email VARCHAR(255) NOT NULL, payer_first_name VARCHAR(255) NOT NULL, payer_last_name VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_A1B39AB64C3A3BB (payment_id), UNIQUE INDEX UNIQ_A1B39AB6833D8F43 (registration_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE commission (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, email VARCHAR(255) NOT NULL, next_meeting_desc VARCHAR(255) DEFAULT NULL, next_meeting_date DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', UNIQUE INDEX UNIQ_404021BF5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE proxy (id INT AUTO_INCREMENT NOT NULL, event_id INT DEFAULT NULL, owner INT DEFAULT NULL, giver INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_7372C9BE71F7E88B (event_id), INDEX IDX_7372C9BECF60E67C (owner), INDEX IDX_7372C9BE5DE37FD9 (giver), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membership (id INT AUTO_INCREMENT NOT NULL, last_registration_id INT DEFAULT NULL, main_beneficiary_id INT DEFAULT NULL, member_number INT NOT NULL, withdrawn TINYINT(1) DEFAULT \'0\', frozen TINYINT(1) DEFAULT \'0\', frozen_change TINYINT(1) DEFAULT \'0\', first_shift_date DATE DEFAULT NULL, UNIQUE INDEX UNIQ_86FFD2856986CF73 (last_registration_id), UNIQUE INDEX UNIQ_86FFD28562C6E4EA (main_beneficiary_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE swipe_card (id INT AUTO_INCREMENT NOT NULL, beneficiary_id INT DEFAULT NULL, created_at DATETIME NOT NULL, disabled_at DATETIME DEFAULT NULL, number INT NOT NULL, enable TINYINT(1) DEFAULT \'0\', code VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_9CD0127677153098 (code), INDEX IDX_9CD01276ECCAAFA0 (beneficiary_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD6819EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD68A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE users_clients ADD CONSTRAINT FK_F0C85ABEA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users_clients ADD CONSTRAINT FK_F0C85ABE19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F219519EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE anonymous_beneficiary ADD CONSTRAINT FK_4864BDFAD1AA2FC1 FOREIGN KEY (registrar_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14F675F31B FOREIGN KEY (author_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA141FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id)');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14727ACA70 FOREIGN KEY (parent_id) REFERENCES note (id)');
        $this->addSql('ALTER TABLE time_log ADD CONSTRAINT FK_55BE03AF1FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE time_log ADD CONSTRAINT FK_55BE03AFBB70BC0E FOREIGN KEY (shift_id) REFERENCES shift (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE code ADD CONSTRAINT FK_77153098D1AA2FC1 FOREIGN KEY (registrar_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE auth_code ADD CONSTRAINT FK_5933D02C19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE auth_code ADD CONSTRAINT FK_5933D02CA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE period_position ADD CONSTRAINT FK_2367D4965200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE period_position_period ADD CONSTRAINT FK_A0A94FFFA95DF5B1 FOREIGN KEY (period_position_id) REFERENCES period_position (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE period_position_period ADD CONSTRAINT FK_A0A94FFFEC8B7ADE FOREIGN KEY (period_id) REFERENCES period (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45A7DA74C1 FOREIGN KEY (shifter_id) REFERENCES beneficiary (id)');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B458B7E4006 FOREIGN KEY (booker_id) REFERENCES beneficiary (id)');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B454EAE1E2B FOREIGN KEY (last_shifter_id) REFERENCES beneficiary (id)');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B455200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECEBE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25D1AA2FC1 FOREIGN KEY (registrar_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tasks_commissions ADD CONSTRAINT FK_C14A17118DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tasks_commissions ADD CONSTRAINT FK_C14A1711202D1EB2 FOREIGN KEY (commission_id) REFERENCES commission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tasks_beneficiaries ADD CONSTRAINT FK_1C93D30B8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tasks_beneficiaries ADD CONSTRAINT FK_1C93D30BECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A71FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7D1AA2FC1 FOREIGN KEY (registrar_id) REFERENCES fos_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446AF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id)');
        $this->addSql('ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446AA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446A1FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446A202D1EB2 FOREIGN KEY (commission_id) REFERENCES commission (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE beneficiaries_commissions ADD CONSTRAINT FK_F87A72B6ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beneficiaries_commissions ADD CONSTRAINT FK_F87A72B6202D1EB2 FOREIGN KEY (commission_id) REFERENCES commission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beneficiaries_formations ADD CONSTRAINT FK_4B438FE7ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beneficiaries_formations ADD CONSTRAINT FK_4B438FE75200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE helloasso_payment ADD CONSTRAINT FK_A1B39AB6833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE proxy ADD CONSTRAINT FK_7372C9BE71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE proxy ADD CONSTRAINT FK_7372C9BECF60E67C FOREIGN KEY (owner) REFERENCES beneficiary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE proxy ADD CONSTRAINT FK_7372C9BE5DE37FD9 FOREIGN KEY (giver) REFERENCES membership (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD2856986CF73 FOREIGN KEY (last_registration_id) REFERENCES registration (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD28562C6E4EA FOREIGN KEY (main_beneficiary_id) REFERENCES beneficiary (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE swipe_card ADD CONSTRAINT FK_9CD01276ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE access_token DROP FOREIGN KEY FK_B6A2DD6819EB6921');
        $this->addSql('ALTER TABLE users_clients DROP FOREIGN KEY FK_F0C85ABE19EB6921');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F219519EB6921');
        $this->addSql('ALTER TABLE auth_code DROP FOREIGN KEY FK_5933D02C19EB6921');
        $this->addSql('ALTER TABLE access_token DROP FOREIGN KEY FK_B6A2DD68A76ED395');
        $this->addSql('ALTER TABLE users_clients DROP FOREIGN KEY FK_F0C85ABEA76ED395');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F2195A76ED395');
        $this->addSql('ALTER TABLE anonymous_beneficiary DROP FOREIGN KEY FK_4864BDFAD1AA2FC1');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14F675F31B');
        $this->addSql('ALTER TABLE code DROP FOREIGN KEY FK_77153098D1AA2FC1');
        $this->addSql('ALTER TABLE auth_code DROP FOREIGN KEY FK_5933D02CA76ED395');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25D1AA2FC1');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7D1AA2FC1');
        $this->addSql('ALTER TABLE beneficiary DROP FOREIGN KEY FK_7ABF446AA76ED395');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455ED5CA9E6');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14727ACA70');
        $this->addSql('ALTER TABLE period_position_period DROP FOREIGN KEY FK_A0A94FFFA95DF5B1');
        $this->addSql('ALTER TABLE beneficiary DROP FOREIGN KEY FK_7ABF446AF5B7AF75');
        $this->addSql('ALTER TABLE proxy DROP FOREIGN KEY FK_7372C9BE71F7E88B');
        $this->addSql('ALTER TABLE time_log DROP FOREIGN KEY FK_55BE03AFBB70BC0E');
        $this->addSql('ALTER TABLE period_position_period DROP FOREIGN KEY FK_A0A94FFFEC8B7ADE');
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45BE04EA9');
        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEBE04EA9');
        $this->addSql('ALTER TABLE tasks_commissions DROP FOREIGN KEY FK_C14A17118DB60186');
        $this->addSql('ALTER TABLE tasks_beneficiaries DROP FOREIGN KEY FK_1C93D30B8DB60186');
        $this->addSql('ALTER TABLE helloasso_payment DROP FOREIGN KEY FK_A1B39AB6833D8F43');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD2856986CF73');
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45A7DA74C1');
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B458B7E4006');
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B454EAE1E2B');
        $this->addSql('ALTER TABLE tasks_beneficiaries DROP FOREIGN KEY FK_1C93D30BECCAAFA0');
        $this->addSql('ALTER TABLE beneficiaries_commissions DROP FOREIGN KEY FK_F87A72B6ECCAAFA0');
        $this->addSql('ALTER TABLE beneficiaries_formations DROP FOREIGN KEY FK_4B438FE7ECCAAFA0');
        $this->addSql('ALTER TABLE proxy DROP FOREIGN KEY FK_7372C9BECF60E67C');
        $this->addSql('ALTER TABLE membership DROP FOREIGN KEY FK_86FFD28562C6E4EA');
        $this->addSql('ALTER TABLE swipe_card DROP FOREIGN KEY FK_9CD01276ECCAAFA0');
        $this->addSql('ALTER TABLE tasks_commissions DROP FOREIGN KEY FK_C14A1711202D1EB2');
        $this->addSql('ALTER TABLE beneficiary DROP FOREIGN KEY FK_7ABF446A202D1EB2');
        $this->addSql('ALTER TABLE beneficiaries_commissions DROP FOREIGN KEY FK_F87A72B6202D1EB2');
        $this->addSql('ALTER TABLE period_position DROP FOREIGN KEY FK_2367D4965200282E');
        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B455200282E');
        $this->addSql('ALTER TABLE beneficiaries_formations DROP FOREIGN KEY FK_4B438FE75200282E');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA141FB354CD');
        $this->addSql('ALTER TABLE time_log DROP FOREIGN KEY FK_55BE03AF1FB354CD');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A71FB354CD');
        $this->addSql('ALTER TABLE beneficiary DROP FOREIGN KEY FK_7ABF446A1FB354CD');
        $this->addSql('ALTER TABLE proxy DROP FOREIGN KEY FK_7372C9BE5DE37FD9');
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE fos_user');
        $this->addSql('DROP TABLE users_clients');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE anonymous_beneficiary');
        $this->addSql('DROP TABLE note');
        $this->addSql('DROP TABLE time_log');
        $this->addSql('DROP TABLE code');
        $this->addSql('DROP TABLE auth_code');
        $this->addSql('DROP TABLE period_position');
        $this->addSql('DROP TABLE period_position_period');
        $this->addSql('DROP TABLE address');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE shift');
        $this->addSql('DROP TABLE period');
        $this->addSql('DROP TABLE job');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE tasks_commissions');
        $this->addSql('DROP TABLE tasks_beneficiaries');
        $this->addSql('DROP TABLE registration');
        $this->addSql('DROP TABLE beneficiary');
        $this->addSql('DROP TABLE beneficiaries_commissions');
        $this->addSql('DROP TABLE beneficiaries_formations');
        $this->addSql('DROP TABLE helloasso_payment');
        $this->addSql('DROP TABLE commission');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE proxy');
        $this->addSql('DROP TABLE membership');
        $this->addSql('DROP TABLE swipe_card');
    }
}
