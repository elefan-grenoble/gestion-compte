<?php

namespace App\Anonymization;

use Doctrine\DBAL\Connection;

/**
 * Guarantees the anonymized copy always has a login every developer
 * already knows, on top of whatever the manifest already rewrote.
 *
 * fos_user.username is anonymized like any other column (see
 * RuleRegistry::username()), so which random name ends up holding
 * ROLE_SUPER_ADMIN changes from one export to the next — picking up a
 * fresh dump means going looking for it first. This repoints one
 * existing super-admin row at a fixed, well-known identity instead,
 * rather than inventing an account the manifest never classified.
 *
 * Runs after Anonymizer, not as one of its rules: the row it touches
 * already has its anonymized password from the normal pass, and the
 * engine itself stays ignorant of what "admin" means (see Anonymizer's
 * own docblock) — that knowledge lives here, one level up.
 */
final class AdminAccountGuarantee
{
    public const USERNAME = 'admin';

    private const TABLE = 'fos_user';

    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws \RuntimeException if no ROLE_SUPER_ADMIN account exists to
     *                           repoint — silently skipping would turn
     *                           "always" into "usually", which is worse
     *                           than refusing
     */
    public function apply(string $emailDomain): void
    {
        // Lowest id: deterministic across runs against the same source,
        // so which account becomes "admin" does not depend on restore
        // ordering or which scratch database ran the query.
        $id = $this->connection->fetchOne(sprintf(
            "SELECT id FROM %s WHERE roles LIKE '%%ROLE_SUPER_ADMIN%%' ORDER BY id ASC LIMIT 1",
            self::TABLE
        ));

        if (false === $id) {
            throw new \RuntimeException(sprintf(
                'No %s account holds ROLE_SUPER_ADMIN; cannot guarantee an "%s" login.',
                self::TABLE,
                self::USERNAME
            ));
        }

        $email = self::USERNAME . '@' . $emailDomain;

        $this->connection->executeStatement(
            sprintf(
                'UPDATE %s SET username = ?, username_canonical = ?, email = ?, email_canonical = ? WHERE id = ?',
                self::TABLE
            ),
            [self::USERNAME, self::USERNAME, $email, $email, (int) $id]
        );
    }
}
