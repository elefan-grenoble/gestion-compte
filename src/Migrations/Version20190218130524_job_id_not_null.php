<?php declare(strict_types=1);

namespace App\Migrations;

use App\Entity\Job;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190218130524_job_id_not_null extends AbstractMigration implements ContainerAwareInterface
{

    use ContainerAwareTrait;

    public function up(Schema $schema) : void
    {

        $connection = $this->container->get('doctrine.orm.entity_manager')->getConnection();
        $result = $connection->fetchColumn('select id from job limit 1');

        if (false === $result) {
            $connection->exec('INSERT INTO job(name, color) VALUES("default", "teal")');
            $jobId = $connection->lastInsertId();
        } else {
            $jobId = $result;
        }


        $this->addSql('UPDATE period SET job_id = '.$jobId.' WHERE job_id IS NULL');
        $this->addSql('UPDATE shift SET job_id = '.$jobId.' WHERE job_id IS NULL');

        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45BE04EA9');
        $this->addSql('ALTER TABLE shift CHANGE job_id job_id INT NOT NULL;');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)');

        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEBE04EA9');
        $this->addSql('ALTER TABLE period CHANGE job_id job_id INT NOT NULL;');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECEBE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)');
    }

    public function down(Schema $schema) : void
    {

        $this->addSql('ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45BE04EA9');
        $this->addSql('ALTER TABLE shift CHANGE job_id job_id INT;');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT FK_A50B3B45BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)');

        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEBE04EA9');
        $this->addSql('ALTER TABLE period CHANGE job_id job_id INT;');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECEBE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)');
    }
}
