<?php

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\Security\KnownOpenVulnerability;

/**
 * C-SEC-1 (SEC.2-1, SEC.3-4): POST /member/{id}/set_email replaces the email
 * of an account that still has a temporary one, with neither a login nor a
 * CSRF token. Whoever sets it then resets the password: account takeover.
 *
 * @internal
 */
class SetEmailSecurityTest extends FunctionalTestCase
{
    use KnownOpenVulnerability;

    private const TEMPORARY_EMAIL = 'membres+4242@yourcoop.local';

    public function testAnonymousVisitorCannotSetTheEmailOfATemporaryAccount(): void
    {
        $client = static::createClient();
        $user = $this->aMemberWithATemporaryEmail();

        $client->request('POST', sprintf('/member/%d/set_email', $user->getBeneficiary()->getId()), [
            'email' => 'attacker@example.test',
        ]);

        $this->assertSecureOrKnownOpen('C-SEC-1', 'anyone can set the email of a temporary-email account', function () use ($user) {
            $this->assertSame(self::TEMPORARY_EMAIL, $this->reloaded($user)->getEmail());
        });
    }

    /**
     * A logged-in member sending the form of another member, without a CSRF
     * token: the same takeover from a forged cross-site POST.
     */
    public function testAnotherMemberCannotSetTheEmailOfATemporaryAccount(): void
    {
        static::createClient();
        $victim = $this->aMemberWithATemporaryEmail();
        $attacker = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary()->getUser();

        $client = static::createAuthenticatedClient($attacker);
        $client->request('POST', sprintf('/member/%d/set_email', $victim->getBeneficiary()->getId()), [
            'email' => 'attacker@example.test',
        ]);

        $this->assertSecureOrKnownOpen('C-SEC-1', 'any member can set the email of another temporary-email account', function () use ($victim) {
            $this->assertSame(self::TEMPORARY_EMAIL, $this->reloaded($victim)->getEmail());
        });
    }

    private function aMemberWithATemporaryEmail(): User
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withUser(UserBuilder::aUser()->withEmail(self::TEMPORARY_EMAIL)))
                ->build()
        )->getMainBeneficiary()->getUser();
    }

    private function reloaded(User $user): User
    {
        $em = static::entityManager();
        $em->clear();

        return $em->find(User::class, $user->getId());
    }
}
