<?php

namespace App\Tests\Unit\Anonymization;

use App\Anonymization\LeakScanner;
use App\Anonymization\RuleRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class LeakScannerTest extends TestCase
{
    public function testCleanTextProducesNoFinding(): void
    {
        $scanner = new LeakScanner(['Dupont']);

        $findings = $scanner->scanText(
            'row',
            'sophie morizot, smorizot_12@example.invalid, 0600000000, ' . password_hash(RuleRegistry::DEFAULT_PASSWORD, PASSWORD_BCRYPT)
        );

        $this->assertSame([], $findings);
    }

    public function testCanaryIsDetected(): void
    {
        $scanner = new LeakScanner(['Bouchard']);

        $findings = $scanner->scanText('beneficiary.lastname', 'Bouchard');

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('real value "Bouchard" survived', $findings[0]);
    }

    public function testCanaryMatchIsCaseInsensitive(): void
    {
        $scanner = new LeakScanner(['Bouchard']);

        $this->assertNotEmpty($scanner->scanText('note.text', 'appelé BOUCHARD hier'));
    }

    /**
     * Short canaries would match inside unrelated words and drown the
     * report, so they are dropped rather than reported badly.
     */
    public function testVeryShortCanariesAreIgnored(): void
    {
        $scanner = new LeakScanner(['Li']);

        $this->assertSame([], $scanner->scanText('row', 'Lille'));
    }

    public function testForeignEmailIsDetected(): void
    {
        $findings = (new LeakScanner())->scanText('fos_user.email', 'jean.dupont@gmail.com');

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('is not on example.invalid', $findings[0]);
    }

    public function testAllowedLiteralExemptsDeliberatelyKeptContent(): void
    {
        $scanner = new LeakScanner([], ['contact@elefan.org']);

        $this->assertSame([], $scanner->scanText('dynamic_content.content', 'Écrivez à contact@elefan.org'));
    }

    public function testHashOfSomeOtherPasswordIsDetected(): void
    {
        $findings = (new LeakScanner())->scanText(
            'fos_user.password',
            password_hash('the-real-users-password', PASSWORD_BCRYPT)
        );

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('real credential', $findings[0]);
    }

    /**
     * bcrypt salts every call differently, so the check cannot compare
     * against a known hash — two hashes of the same password must both
     * pass.
     */
    public function testAnyHashOfTheAnonymizedPasswordIsAccepted(): void
    {
        $scanner = new LeakScanner();

        $this->assertSame([], $scanner->scanText('a', password_hash(RuleRegistry::DEFAULT_PASSWORD, PASSWORD_BCRYPT)));
        $this->assertSame([], $scanner->scanText('b', password_hash(RuleRegistry::DEFAULT_PASSWORD, PASSWORD_BCRYPT)));
    }

    public function testTheExpectedPasswordCanBeOverridden(): void
    {
        $scanner = new LeakScanner([], [], 'chosen-at-export-time');

        $this->assertSame([], $scanner->scanText('ok', password_hash('chosen-at-export-time', PASSWORD_BCRYPT)));
        $this->assertCount(1, $scanner->scanText('ko', password_hash(RuleRegistry::DEFAULT_PASSWORD, PASSWORD_BCRYPT)));
    }

    public function testHashCheckCanBeDisabled(): void
    {
        $scanner = new LeakScanner([], [], null);

        $this->assertSame([], $scanner->scanText('row', password_hash('anything at all', PASSWORD_BCRYPT)));
    }

    /**
     * @dataProvider realPhoneNumbers
     */
    public function testRealPhoneNumberIsDetected(string $phone): void
    {
        $findings = (new LeakScanner())->scanText('beneficiary.phone', $phone);

        $this->assertCount(1, $findings, sprintf('Expected "%s" to be reported.', $phone));
    }

    public function realPhoneNumbers(): array
    {
        return [
            'plain' => ['0612345678'],
            'spaced' => ['06 12 34 56 78'],
            'dotted' => ['06.12.34.56.78'],
            'international' => ['+33612345678'],
        ];
    }

    public function testPlaceholderPhoneNumbersAreAccepted(): void
    {
        $this->assertSame([], (new LeakScanner())->scanText('beneficiary.phone', '0600000000'));
    }

    /**
     * Ids, amounts and timestamps sit next to phone numbers in a dump and
     * must not be mistaken for them.
     */
    public function testNumbersThatAreNotPhonesAreIgnored(): void
    {
        $this->assertSame([], (new LeakScanner())->scanText('row', '(12345,1,0,2024-01-02 03:04:05,19.99)'));
    }
}
