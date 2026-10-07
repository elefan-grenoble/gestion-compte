<?php

namespace App\Tests\Functional\Command;

use App\Entity\Membership;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;

/**
 * app:beneficiary:randomise lists, in random order, the beneficiaries whose
 * membership was registered in the year before a given date (the date of an
 * event, say). The order is random: the tests look at who is listed.
 *
 * @internal
 */
class RandomSortMembersCommandTest extends CommandTestCase
{
    private const EVENT_DATE = '2026-06-15';

    /** @var string */
    private $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'randomise');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    public function testListsTheBeneficiariesRegisteredInTheYearBeforeTheDate(): void
    {
        $registeredOneDayLater = $this->membershipRegisteredOn('2025-06-16');
        $registeredLongAfter = $this->membershipRegisteredOn('2026-05-01');
        $this->membershipRegisteredOn('2025-06-15'); // a year to the day: expired for the event
        $this->membershipRegisteredOn('2024-01-01');

        $tester = $this->runCommand('app:beneficiary:randomise', ['date' => self::EVENT_DATE, '--file' => $this->file]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertEqualsCanonicalizing(
            [$registeredOneDayLater->getMemberNumber(), $registeredLongAfter->getMemberNumber()],
            $this->listedMemberNumbers()
        );
        $this->assertStringContainsString('2 beneficiaires à jour', $tester->getDisplay());
    }

    public function testWritesACsvWithTheDetailsOfEachBeneficiary(): void
    {
        $membership = MembershipBuilder::aMembership()
            ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Ada', 'Lovelace'))
            ->registeredOn(new \DateTime('2026-01-10'))
            ->build();
        $membership->getMainBeneficiary()->setPhone('0612345678');
        static::persist($membership);

        $this->runCommand('app:beneficiary:randomise', ['date' => self::EVENT_DATE, '--file' => $this->file]);

        $lines = file($this->file, FILE_IGNORE_NEW_LINES);
        $this->assertSame('Index, Numéro de membre, Prénom, Nom, Téléphone, Email', $lines[0]);
        $this->assertCount(2, $lines);
        $this->assertSame(
            '1,' . $membership->getMemberNumber() . ',Ada,LOVELACE,612345678,' . $membership->getMainBeneficiary()->getEmail() . ',',
            $lines[1],
            'the phone goes through intval(): the leading 0 is lost'
        );
    }

    public function testListsEveryBeneficiaryOfAMembership(): void
    {
        $membership = static::persist(MembershipBuilder::aMembership()
            ->withBeneficiary(BeneficiaryBuilder::aBeneficiary())
            ->registeredOn(new \DateTime('2026-01-10'))
            ->build());

        $this->runCommand('app:beneficiary:randomise', ['date' => self::EVENT_DATE, '--file' => $this->file]);

        $this->assertSame(
            [$membership->getMemberNumber(), $membership->getMemberNumber()],
            $this->listedMemberNumbers()
        );
    }

    public function testOnlyTheLastRegistrationCounts(): void
    {
        $renewed = $this->membershipRegisteredOn('2020-01-01', '2026-01-10');
        $this->membershipRegisteredOn('2020-01-01', '2021-01-01');

        $this->runCommand('app:beneficiary:randomise', ['date' => self::EVENT_DATE, '--file' => $this->file]);

        $this->assertSame([$renewed->getMemberNumber()], $this->listedMemberNumbers(), 'listed once, for its latest registration');
    }

    public function testLeavesOutTheWithdrawnMemberships(): void
    {
        $active = $this->membershipRegisteredOn('2026-01-10');
        static::persist(MembershipBuilder::aMembership()->withdrawn()->registeredOn(new \DateTime('2026-01-10'))->build());

        $this->runCommand('app:beneficiary:randomise', ['date' => self::EVENT_DATE, '--file' => $this->file]);

        $this->assertSame([$active->getMemberNumber()], $this->listedMemberNumbers());
    }

    public function testFrozenMembershipsAreListedUnlessExcluded(): void
    {
        $active = $this->membershipRegisteredOn('2026-01-10');
        $frozen = static::persist(MembershipBuilder::aMembership()->frozen()->registeredOn(new \DateTime('2026-01-10'))->build());

        $tester = $this->runCommand('app:beneficiary:randomise', ['date' => self::EVENT_DATE, '--file' => $this->file]);
        $this->assertEqualsCanonicalizing([$active->getMemberNumber(), $frozen->getMemberNumber()], $this->listedMemberNumbers());
        $this->assertStringContainsString('les comptes gelés sont inclus', $tester->getDisplay());

        $tester = $this->runCommand('app:beneficiary:randomise', ['date' => self::EVENT_DATE, '--file' => $this->file, '--exclude_frozen' => true]);
        $this->assertSame([$active->getMemberNumber()], $this->listedMemberNumbers());
        $this->assertStringContainsString('ne pas inclure les comptes gelés', $tester->getDisplay());
    }

    public function testMaxDateKeepsTheMembershipsRegisteredUpToThatDay(): void
    {
        $early = $this->membershipRegisteredOn('2025-09-01');
        $onTheLastDay = $this->membershipRegisteredOn('2025-12-31');
        $this->membershipRegisteredOn('2026-01-01');

        $this->runCommand('app:beneficiary:randomise', ['date' => self::EVENT_DATE, '--file' => $this->file, '--max_date' => '2025-12-31']);

        $this->assertEqualsCanonicalizing([$early->getMemberNumber(), $onTheLastDay->getMemberNumber()], $this->listedMemberNumbers());
    }

    public function testListsEveryoneOnceWhateverTheOrder(): void
    {
        $expected = [];
        for ($i = 0; $i < 12; ++$i) {
            $expected[] = $this->membershipRegisteredOn('2026-01-10')->getMemberNumber();
        }

        $this->runCommand('app:beneficiary:randomise', ['date' => self::EVENT_DATE, '--file' => $this->file]);

        $this->assertEqualsCanonicalizing($expected, $this->listedMemberNumbers());
    }

    /**
     * @dataProvider wrongDatesProvider
     */
    public function testRejectsAMalformedDate(array $input, string $message): void
    {
        $this->membershipRegisteredOn('2026-01-10');

        $tester = $this->runCommand('app:beneficiary:randomise', $input + ['--file' => $this->file]);

        $this->assertSame(2, $tester->getStatusCode());
        $this->assertStringContainsString($message, $tester->getDisplay());
        $this->assertSame('', (string) file_get_contents($this->file), 'nothing written');
    }

    public function wrongDatesProvider(): array
    {
        return [
            'date' => [['date' => '15/06/2026'], 'wrong date format for minimum date'],
            'impossible date' => [['date' => '2026-02-30'], 'wrong date format for minimum date'],
            'max date' => [['date' => self::EVENT_DATE, '--max_date' => 'soon'], 'wrong date format for maximum date'],
        ];
    }

    /**
     * @return int[] the member numbers listed in the file, one per row
     */
    private function listedMemberNumbers(): array
    {
        $lines = file($this->file, FILE_IGNORE_NEW_LINES);
        array_shift($lines); // header

        return array_map(static function (string $line) {
            return (int) explode(',', $line)[1];
        }, $lines);
    }

    private function membershipRegisteredOn(string ...$dates): Membership
    {
        return static::persist(MembershipBuilder::aMembership()->registeredOn(...array_map(static function (string $date) {
            return new \DateTime($date);
        }, $dates))->build());
    }
}
