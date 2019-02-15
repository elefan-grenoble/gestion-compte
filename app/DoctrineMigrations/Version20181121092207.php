<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181121092207 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP VIEW IF EXISTS abstract_registration');
        $this->addSql('DROP TABLE IF EXISTS abstract_registration');

        $this->addSql('CREATE ALGORITHM=TEMPTABLE VIEW abstract_registration AS (SELECT CONCAT(\'1_\',registration.id) as id,1 as type,registrar_id,created_at as date,amount,mode,CONCAT(LOWER(beneficiary.firstname),\' \',UPPER(beneficiary.lastname)) as beneficiary, registration.membership_id
       FROM registration
       LEFT JOIN membership ON registration.membership_id = membership.id
       LEFT JOIN beneficiary ON beneficiary.id = membership.main_beneficiary_id
      ) UNION ALL
      (SELECT CONCAT(\'2_\',id) as id,2 as type,registrar_id,created_at as date,amount,mode,email as beneficiary, NULL as membership_id
       FROM anonymous_beneficiary
      );');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP VIEW abstract_registration');
    }
}
