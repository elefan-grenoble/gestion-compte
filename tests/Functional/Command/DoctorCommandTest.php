<?php

namespace App\Tests\Functional\Command;

use App\Entity\Beneficiary;
use App\Tests\Support\Builder\MembershipBuilder;

/**
 * app:doc repairs data that older versions of the application let in. Only
 * the phone repair (--phone) can be exercised: the other two fix states that
 * the schema no longer allows (NULL statuses) or that are not reachable from
 * the entities.
 *
 * @internal
 */
class DoctorCommandTest extends CommandTestCase
{
    /**
     * @dataProvider phonesProvider
     */
    public function testRepairsThePhoneNumbers(string $phone, ?string $repaired): void
    {
        $beneficiary = $this->aBeneficiaryWithPhone($phone);

        $tester = $this->runCommand('app:doc', ['--phone' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame($repaired, $this->reload($beneficiary)->getPhone());
        $this->assertStringContainsString($repaired === $phone ? '0 numéro(s) corrigé(s)' : '1 numéro(s) corrigé(s)', $tester->getDisplay());
    }

    public function phonesProvider(): array
    {
        return [
            'already right' => ['0612345678', '0612345678'],
            'spaces' => ['06 12 34 56 78', '0612345678'],
            'dots' => ['06.12.34.56.78', '0612345678'],
            'commas' => ['06,12,34,56,78', '0612345678'],
            'slashes' => ['06/12/34/56/78', '0612345678'],
            'backslashes' => ['06\\12\\34\\56\\78', '0612345678'],
            'missing leading 0' => ['612345678', '0612345678'],
            'two leading 0' => ['00612345678', '0612345678'],
            'only 0' => ['0000', null],
        ];
    }

    public function testLeavesTheBeneficiariesWithoutAPhoneAlone(): void
    {
        $beneficiary = $this->aBeneficiaryWithPhone(null);

        $tester = $this->runCommand('app:doc', ['--phone' => true]);

        $this->assertNull($this->reload($beneficiary)->getPhone());
        $this->assertStringContainsString('0 numéro(s) corrigé(s)', $tester->getDisplay());
    }

    public function testDoesNothingWithoutOptions(): void
    {
        $beneficiary = $this->aBeneficiaryWithPhone('06 12 34 56 78');

        $tester = $this->runCommand('app:doc');

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame('06 12 34 56 78', $this->reload($beneficiary)->getPhone());
        $this->assertStringNotContainsString('PHONES FIX', $tester->getDisplay());
    }

    public function testAnEmptyDatabaseNeedsNoRepair(): void
    {
        $tester = $this->runCommand('app:doc', ['--phone' => true, '--status' => true, '--registration' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('0 numéro(s) corrigé(s)', $tester->getDisplay());
        $this->assertStringContainsString('0 status vide(s) corrigé(s)', $tester->getDisplay());
        $this->assertStringContainsString('0 correction(s) apportée(s) aux adhésion(s)', $tester->getDisplay());
    }

    private function aBeneficiaryWithPhone(?string $phone): Beneficiary
    {
        $membership = MembershipBuilder::aMembership()->build();
        $membership->getMainBeneficiary()->setPhone($phone);

        return static::persist($membership)->getMainBeneficiary();
    }

    private function reload(Beneficiary $beneficiary): Beneficiary
    {
        $id = $beneficiary->getId();
        $em = static::entityManager();
        $em->clear();

        return $em->find(Beneficiary::class, $id);
    }
}
