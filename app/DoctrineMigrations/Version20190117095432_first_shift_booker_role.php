<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190117095432_first_shift_booker_role extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    }

    public function postUp(Schema $schema)
    {
        parent::postUp($schema);

        // Get all users with at least a shift
        $rs = $this->connection->fetchAll(
            'SELECT u.id, u.roles 
                  FROM fos_user u
                  LEFT JOIN beneficiary b ON b.user_id = u.id
                  LEFT JOIN shift s ON s.booker_id = b.id
                  WHERE s.id IS NOT NULL
                  '
        );

        // Apply the role to each of those users
        foreach ($rs as $user) {
            $roles = unserialize($user['roles']);
            if (!in_array('ROLE_SHIFT_FIRST_BOOKER', $roles)) {
                $roles[] = 'ROLE_SHIFT_FIRST_BOOKER';

                $newRoles = serialize($roles);

                $this->connection->update('fos_user', [
                    'roles' => $newRoles
                ], [
                    'id' => $user['id']
                ]);
            }
        }
    }
}
