<?php

namespace App\Anonymization;

use App\Entity\User;

/**
 * The set of replacement rules a column can be given in
 * config/anonymization.yaml, and the values they produce.
 *
 * Every value is derived from an integer seed, so two runs over the same
 * database produce the same output, and two tables seeded on the same
 * user id draw the same identity. That is what keeps `fos_user` and
 * `beneficiary` telling the same story without needing a join.
 *
 * The only deliberate exception is `token`, which must be unpredictable.
 */
final class RuleRegistry
{
    public const KEEP = 'keep';

    /**
     * RFC 6761 reserves `.invalid` as never-resolvable. Rewriting e-mail
     * addresses onto it means a developer instance pointed at real SMTP
     * cannot reach a real person by accident — which rewriting only the
     * local part, and keeping the original domain, would still allow.
     */
    public const EMAIL_DOMAIN = 'example.invalid';

    /**
     * Every anonymized account ends up with the same password, so the
     * export can be logged into. Overridable per run; the plaintext is
     * not a secret, it is a published property of the artifact.
     */
    public const DEFAULT_PASSWORD = 'Password123';

    public const REDACTED = '[anonymized]';

    /** @var string */
    private $password;

    /**
     * Hashed once and reused for every row: bcrypt salts each call
     * differently, so hashing per row would produce a different hash per
     * user for the same password — more work, and it would defeat the
     * "all accounts share one password" check the verifier relies on.
     *
     * @var string|null
     */
    private $passwordHash;

    public function __construct(string $password = self::DEFAULT_PASSWORD)
    {
        if ('' === $password) {
            throw new \InvalidArgumentException('The anonymized password cannot be empty.');
        }

        $this->password = $password;
    }

    public function password(): string
    {
        return $this->password;
    }

    /** @var string[] */
    private $firstnames = [
        'sophie', 'marie', 'noemie', 'helene', 'chloe', 'laura', 'lily', 'celeste', 'capucine', 'zoe',
        'julie', 'charlotte', 'paul', 'stephane', 'manu', 'tim', 'pierre', 'florian', 'clement',
        'julien', 'baptiste', 'bruno', 'arthur', 'charles', 'perrine', 'raphael', 'gauthier',
        'antoine', 'alain', 'dominique', 'nancy', 'david', 'philippe',
    ];

    /** @var string[] */
    private $lastnames = [
        'morizot', 'servigne', 'mignerot', 'keller', 'damasio', 'bidar', 'bourg', 'chapelle',
        'huston', 'stevens', 'quinn', 'rabhi', 'latour', 'spinoza', 'holmgren', 'kropotkine',
        'descola', 'mollison',
    ];

    /** @var string[] */
    private $streets = [
        '13 rue de la chance', '666 rue des anges', '321 avenue du top depart', '1989 rue du mur',
        '1788 rue des tuiles', '0 rue du capital', '99 rue de la promo', 'Place de la joie',
        'Impasse de la croissance', '1ter rue du partage', 'Boulevard des possibles',
    ];

    /**
     * Zip code and city are drawn together from this list so that a row
     * never ends up with a zip code that contradicts its city.
     *
     * @var string[][]
     */
    private $cities = [
        ['38000', 'Grenoble'],
        ['26150', 'Die'],
        ['26340', 'Saillans'],
        ['38220', 'Vizille'],
        ['27800', 'Bec-Hellouin'],
        ['35000', 'Rennes'],
    ];

    /** @var string[] */
    private $phones = ['0600000000', '0400000000', '0500000000'];

    /** @var string[] */
    private $words = ['alpha', 'bravo', 'charlie', 'delta', 'echo', 'foxtrot', 'golf', 'hotel'];

    /** @var string[] */
    private $texts = [
        'La cooperative est approvisionnee par des producteurs de l economie sociale et solidaire.',
        'La cooperative favorise l acces a une alimentation de qualite pour le plus grand nombre.',
        'Les produits sont vendus au prix d achat, sans aucun profit.',
        'La cooperative est autogeree par ses membres.',
        'Un projet economique alternatif, fonde sur un modele a but non lucratif.',
        'La cooperative pratique la solidarite, individuelle comme collective.',
    ];

    /**
     * @return string[]
     */
    public function names(): array
    {
        return [
            self::KEEP,
            'empty', 'null',
            'firstname', 'lastname', 'phone', 'street', 'city', 'zipcode',
            'username', 'username_canonical', 'email', 'email_canonical',
            'word', 'text', 'password', 'token', 'sequence', 'redact',
        ];
    }

    public function supports(string $rule): bool
    {
        return in_array($rule, $this->names(), true);
    }

    /**
     * Produce the replacement value for one column of one row.
     *
     * @param int $seed identity seed — equal seeds yield equal identities
     * @param int $id   the row's own id, used where the column must stay unique
     *
     * @return string|null null means SQL NULL
     */
    public function value(string $rule, int $seed, int $id): ?string
    {
        switch ($rule) {
            case self::KEEP:
                throw new \LogicException('The "keep" rule produces no value; it must not be applied.');

            case 'empty':
                return '';

            case 'null':
                return null;

            case 'firstname':
                return $this->pick($this->firstnames, $seed);

            case 'lastname':
                return $this->pick($this->lastnames, $seed);

            case 'phone':
                return $this->pick($this->phones, $seed);

            case 'street':
                return $this->pick($this->streets, $seed);

            case 'city':
                return $this->pick($this->cities, $seed)[1];

            case 'zipcode':
                return $this->pick($this->cities, $seed)[0];

            // Written straight to the canonical columns rather than left to
            // the FOSUserBundle Doctrine listener: the export must not
            // depend on an ORM side effect that a future refactor could
            // silently drop.
            case 'username':
            case 'username_canonical':
                return $this->username($seed, $id);

            case 'email':
            case 'email_canonical':
                return $this->username($seed, $id) . '@' . self::EMAIL_DOMAIN;

            case 'word':
                return $this->pick($this->words, $seed) . ' ' . $id;

            case 'text':
                return $this->pick($this->texts, $seed);

            case 'password':
                return $this->passwordHash();

            case 'token':
                return bin2hex(random_bytes(16));

            case 'sequence':
                return (string) $id;

            case 'redact':
                return self::REDACTED;

            default:
                throw new \InvalidArgumentException(sprintf('Unknown anonymization rule "%s".', $rule));
        }
    }

    private function passwordHash(): string
    {
        if (null === $this->passwordHash) {
            $hash = password_hash($this->password, PASSWORD_BCRYPT);
            if (!is_string($hash)) {
                throw new \RuntimeException('Could not hash the anonymized password.');
            }

            $this->passwordHash = $hash;
        }

        return $this->passwordHash;
    }

    /**
     * Unique because of the id suffix: `fos_user.username` and
     * `fos_user.email` both carry a UNIQUE constraint.
     */
    private function username(int $seed, int $id): string
    {
        return User::makeUsername($this->pick($this->firstnames, $seed), $this->pick($this->lastnames, $seed)) . '_' . $id;
    }

    /**
     * @param array<int, mixed> $pool
     *
     * @return mixed
     */
    private function pick(array $pool, int $seed)
    {
        return $pool[abs($seed) % count($pool)];
    }
}
