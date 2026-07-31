<?php

namespace App\Anonymization;

/**
 * Looks for personal data that survived anonymization.
 *
 * One rule set, two consumers: the functional test runs it over every
 * textual value in the anonymized database, and `app:anonymize:verify`
 * runs it over the dump file that actually gets shared. Keeping a single
 * implementation is the point — a check that only guards the test
 * database guarantees nothing about the artifact you hand out.
 *
 * Two kinds of check:
 *
 *  - canaries: exact strings the caller knows to be real personal data.
 *    The functional test plants them before anonymizing, which catches
 *    an entire column being forgotten.
 *
 *  - patterns: shapes that should no longer exist anywhere, whatever the
 *    source data was. This is the half that keeps working on a dump of a
 *    production database, where nobody knows the real values up front.
 */
final class LeakScanner
{
    /** @var string[] */
    private $canaries;

    /** @var string[] */
    private $allowed;

    /** @var string|null */
    private $expectedPassword;

    /**
     * @param string[]    $canaries         known-real values that must not survive
     * @param string[]    $allowed          literals exempt from the pattern rules,
     *                                      for content deliberately left intact
     * @param string|null $expectedPassword the password every account is
     *                                      supposed to end up with; null
     *                                      disables the hash check
     */
    public function __construct(array $canaries = [], array $allowed = [], ?string $expectedPassword = RuleRegistry::DEFAULT_PASSWORD)
    {
        $this->expectedPassword = $expectedPassword;
        $this->canaries = array_values(array_filter($canaries, static function (string $value): bool {
            // Very short strings match everywhere and would only produce noise.
            return mb_strlen($value) >= 4;
        }));
        $this->allowed = $allowed;
    }

    /**
     * @return string[] human-readable findings; empty means clean
     */
    public function scanText(string $label, string $text): array
    {
        $findings = [];

        foreach ($this->canaries as $canary) {
            if (false !== mb_stripos($text, $canary)) {
                $findings[] = sprintf('%s: real value "%s" survived', $label, $canary);
            }
        }

        foreach ($this->emails($text) as $email) {
            $findings[] = sprintf('%s: e-mail address "%s" is not on %s', $label, $email, RuleRegistry::EMAIL_DOMAIN);
        }

        foreach ($this->passwordHashes($text) as $hash) {
            $findings[] = sprintf(
                '%s: password hash "%s..." does not match the anonymized password, so it is somebody\'s real credential',
                $label,
                substr($hash, 0, 20)
            );
        }

        foreach ($this->phoneNumbers($text) as $phone) {
            $findings[] = sprintf('%s: phone number "%s" is not one of the placeholders', $label, $phone);
        }

        return $findings;
    }

    /**
     * @return string[]
     */
    private function emails(string $text): array
    {
        if (!preg_match_all('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u', $text, $matches)) {
            return [];
        }

        $suffix = '@' . RuleRegistry::EMAIL_DOMAIN;

        return array_values(array_unique(array_filter($matches[0], function (string $email) use ($suffix): bool {
            if ($this->isAllowed($email)) {
                return false;
            }

            return substr($email, -strlen($suffix)) !== $suffix;
        })));
    }

    /**
     * A bcrypt hash that does not verify against the anonymized password
     * is somebody's real credential, and bcrypt hashes are worth cracking.
     *
     * Checked with password_verify rather than by comparing to a known
     * hash: bcrypt salts every call differently, so the hash a run
     * produces cannot be predicted — only the password behind it can be
     * confirmed.
     *
     * @return string[]
     */
    private function passwordHashes(string $text): array
    {
        if (null === $this->expectedPassword) {
            return [];
        }

        if (!preg_match_all('/\$2[aby]?\$\d{2}\$[.\/A-Za-z0-9]{53}/', $text, $matches)) {
            return [];
        }

        return array_values(array_unique(array_filter($matches[0], function (string $hash): bool {
            return !password_verify($this->expectedPassword, $hash);
        })));
    }

    /**
     * French numbers, in the shapes the application accepts. Anchored on
     * word boundaries so that ids, amounts and timestamps do not match.
     *
     * @return string[]
     */
    private function phoneNumbers(string $text): array
    {
        if (!preg_match_all('/(?<![\d.])(?:\+33|0)[1-9](?:[ .-]?\d{2}){4}(?![\d.])/', $text, $matches)) {
            return [];
        }

        $rules = new RuleRegistry();
        $known = [];
        for ($seed = 0; $seed < 32; ++$seed) {
            $known[] = $rules->value('phone', $seed, 0);
        }
        $known = array_unique(array_filter($known));

        return array_values(array_unique(array_filter($matches[0], function (string $phone) use ($known): bool {
            $normalized = preg_replace('/[ .-]/', '', $phone);

            return !in_array($normalized, $known, true) && !$this->isAllowed($phone);
        })));
    }

    private function isAllowed(string $value): bool
    {
        foreach ($this->allowed as $allowed) {
            if (0 === strcasecmp($allowed, $value)) {
                return true;
            }
        }

        return false;
    }
}
