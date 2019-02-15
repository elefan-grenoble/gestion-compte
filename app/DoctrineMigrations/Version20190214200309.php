<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190214200309 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('DROP VIEW IF EXISTS abstract_registration');

        $this->addSql('CREATE ALGORITHM=TEMPTABLE VIEW view_abstract_registration AS (SELECT CONCAT(\'1_\',registration.id) as id,1 as type,registrar_id,created_at as date,amount,mode,CONCAT(LOWER(beneficiary.firstname),\' \',UPPER(beneficiary.lastname)) as beneficiary, registration.membership_id
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
        $this->addSql('DROP VIEW IF EXISTS view_abstract_registration');

        $this->addSql('CREATE ALGORITHM=TEMPTABLE VIEW abstract_registration AS (SELECT CONCAT(\'1_\',registration.id) as id,1 as type,registrar_id,created_at as date,amount,mode,CONCAT(LOWER(beneficiary.firstname),\' \',UPPER(beneficiary.lastname)) as beneficiary, registration.membership_id
       FROM registration
       LEFT JOIN membership ON registration.membership_id = membership.id
       LEFT JOIN beneficiary ON beneficiary.id = membership.main_beneficiary_id
      ) UNION ALL
      (SELECT CONCAT(\'2_\',id) as id,2 as type,registrar_id,created_at as date,amount,mode,email as beneficiary, NULL as membership_id
       FROM anonymous_beneficiary
      );');

    }
}
