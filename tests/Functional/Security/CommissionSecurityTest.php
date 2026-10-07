<?php

namespace App\Tests\Functional\Security;

use App\Entity\Beneficiary;
use App\Entity\Commission;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\Security\KnownOpenVulnerability;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * I-SEC-5 (SEC.2-3): adding a member to a commission, or removing one, is
 * authorized by hand in the controller (super admin, or owner of the
 * commission) instead of by @Security or a voter, and the removal reads
 * $_POST directly.
 *
 * @internal
 */
class CommissionSecurityTest extends FunctionalTestCase
{
    use KnownOpenVulnerability;

    /**
     * The original symptom, a fatal error on "anon."->hasRole(), is gone
     * since the default-deny rule (C-SEC-2) sends anonymous visitors to the
     * login page first.
     *
     * @dataProvider membershipChanges
     */
    public function testAnonymousVisitorIsAskedToLogIn(string $change): void
    {
        $client = static::createClient();
        $commission = $this->aCommission();

        $client->request('POST', sprintf('/commissions/%d/%s/', $commission->getId(), $change));

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
    }

    /**
     * @return array<string, array{string}>
     */
    public function membershipChanges(): array
    {
        return ['add' => ['add_beneficiary'], 'remove' => ['remove_beneficiary']];
    }

    public function testAMemberWhoDoesNotOwnTheCommissionCannotRemoveSomeone(): void
    {
        static::createClient();
        $commission = $this->aCommission();
        $member = $this->aMemberOf($commission);

        $client = static::createAuthenticatedClient($this->aMember()->getUser());
        $client->request('POST', sprintf('/commissions/%d/remove_beneficiary/', $commission->getId()), ['beneficiary' => $member->getId()]);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertTrue($this->isStillIn($member, $commission));
    }

    /**
     * An account without a beneficiary (an admin account, say) crashes the
     * hand-written check, which calls getBeneficiary()->getOwnedCommissions().
     *
     * @dataProvider membershipChanges
     */
    public function testAnAccountWithoutBeneficiaryIsRefusedRatherThanCrashing(string $change): void
    {
        static::createClient();
        $commission = $this->aCommission();
        $account = static::persist(UserBuilder::aUser()->withRoles('ROLE_ADMIN')->build());

        $client = static::createAuthenticatedClient($account);
        $outcome = $this->send($client, sprintf('/commissions/%d/%s/', $commission->getId(), $change), []);

        $this->assertSecureOrKnownOpen('I-SEC-5', 'the hand-written check crashes for an account without beneficiary', function () use ($outcome) {
            $this->assertSame(403, $outcome);
        });
    }

    /**
     * The owner's removal reads $_POST, which only the front controller
     * fills: the request the controller is given (here by the test client)
     * is ignored.
     */
    public function testTheOwnerRemovesAMemberFromTheRequestData(): void
    {
        static::createClient();
        $commission = $this->aCommission();
        $owner = $this->anOwnerOf($commission);
        $member = $this->aMemberOf($commission);

        $client = static::createAuthenticatedClient($owner->getUser());
        $outcome = $this->send($client, sprintf('/commissions/%d/remove_beneficiary/', $commission->getId()), ['beneficiary' => $member->getId()]);

        $this->assertSecureOrKnownOpen('I-SEC-5', 'remove_beneficiary reads $_POST instead of the Request', function () use ($outcome, $member, $commission) {
            $this->assertSame(302, $outcome);
            $this->assertFalse($this->isStillIn($member, $commission));
        });
    }

    /**
     * Posts and returns the status code, or a description of the PHP error
     * the controller died of (the test client, unlike the front controller,
     * lets errors through).
     *
     * @param array<string, int|string> $data
     *
     * @return int|string
     */
    private function send(KernelBrowser $client, string $path, array $data)
    {
        try {
            $client->request('POST', $path, $data);
        } catch (\Error $error) {
            return sprintf('crashed: %s: %s', get_class($error), $error->getMessage());
        }

        return $client->getResponse()->getStatusCode();
    }

    private function aCommission(): Commission
    {
        $commission = new Commission();
        $commission->setName('Commission ' . uniqid());
        $commission->setDescription('A commission');
        $commission->setEmail('commission@example.test');

        return static::persist($commission);
    }

    private function aMember(): Beneficiary
    {
        return static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
    }

    private function aMemberOf(Commission $commission): Beneficiary
    {
        $member = $this->aMember();
        $member->addCommission($commission);

        return static::persist($member);
    }

    private function anOwnerOf(Commission $commission): Beneficiary
    {
        $owner = static::persist(
            MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser(UserBuilder::aUser()))->build()
        )->getMainBeneficiary();
        $owner->addCommission($commission);
        $owner->setOwn($commission);
        $commission->addOwner($owner);

        static::persist($owner, $commission);

        return $owner;
    }

    private function isStillIn(Beneficiary $member, Commission $commission): bool
    {
        $em = static::entityManager();
        $em->clear();

        return $em->find(Beneficiary::class, $member->getId())->getCommissions()->exists(
            static function ($key, Commission $each) use ($commission): bool {
                return $each->getId() === $commission->getId();
            }
        );
    }
}
