<?php

namespace App\Tests\Functional\Controller;

use App\Entity\AnonymousBeneficiary;
use App\Entity\Beneficiary;
use App\Entity\Membership;
use App\Entity\User;
use App\Helper\SwipeCard;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\UserBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * Adding, detaching and joining beneficiaries: a membership holds at most
 * MAXIMUM_NB_OF_BENEFICIARIES_IN_MEMBERSHIP of them (2 in .env.test).
 *
 * @internal
 */
class MembershipControllerBeneficiaryTest extends FunctionalTestCase
{
    use ShiftScenarios;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    // --- add a beneficiary from the back office ----------------------------

    public function testAnAdminAddsABeneficiaryToAMembership(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        static::logIn($client, $this->anAdmin());

        $this->postNewBeneficiary($client, $membership, 'Newcomer', 'newcomer@test.local');

        $this->assertSame(['Beneficiaire ajouté'], static::flashes($client)['success'] ?? [], json_encode(static::flashes($client)));
        $reloaded = static::reloaded($membership);
        $this->assertCount(2, $reloaded->getBeneficiaries());
        $added = $reloaded->getBeneficiaries()->filter(function (Beneficiary $b) {
            return 'Newcomer' === $b->getFirstname();
        })->first();
        $this->assertSame($reloaded->getId(), $added->getMembership()->getId());
        $this->assertSame('newcomer@test.local', $added->getUser()->getEmail());
        $this->assertFalse($added->isMain());
    }

    /**
     * A membership holds at most 2 beneficiaries. The BeneficiaryCanHost
     * constraint refuses first; the `count <= max` test of newBeneficiary()
     * behind it would let a third one in, so the constraint is the only guard.
     */
    public function testAMembershipAlreadyAtTheMaximumRefusesAnotherBeneficiary(): void
    {
        $client = static::createClient();
        $membership = $this->aMembershipOfTwo();
        $this->assertCount(2, $membership->getBeneficiaries(), 'Precondition.');
        static::logIn($client, $this->anAdmin());

        $this->postNewBeneficiary($client, $membership, 'Third', 'third@test.local');

        $this->assertCount(2, static::reloaded($membership)->getBeneficiaries());
        $this->assertStringContainsString('nombre maximum', static::flashes($client)['error'][0] ?? '');
    }

    public function testAnInvalidBeneficiaryIsNotAdded(): void
    {
        $client = static::createClient();
        $membership = static::aMembership();
        static::logIn($client, $this->anAdmin());

        $this->postNewBeneficiary($client, $membership, '', 'not-an-email');

        $this->assertNotEmpty(static::flashes($client)['error'] ?? []);
        $this->assertCount(1, static::reloaded($membership)->getBeneficiaries());
    }

    public function testAMemberCannotAddABeneficiaryToAnotherMembership(): void
    {
        $client = static::createClient();
        $me = static::aMembership();
        $other = static::aMembership();
        static::logIn($client, $me->getMainBeneficiary()->getUser());

        $this->postNewBeneficiary($client, $other, 'Intruder', 'intruder@test.local');

        $this->assertContains($client->getResponse()->getStatusCode(), [302, 403]);
        $this->assertCount(1, static::reloaded($other)->getBeneficiaries(), json_encode(static::flashes($client)));
    }

    // --- add a beneficiary through the emailed link ------------------------

    public function testAnInvalidCodeIsRefused(): void
    {
        $client = static::createClient();

        $client->request('GET', '/member/add_beneficiary?code=forged');

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertSame(["Cette url n'est plus valide"], static::flashes($client)['error'] ?? []);
    }

    public function testAnAnonymousBeneficiaryFinalizesTheirRegistrationFromTheLink(): void
    {
        $client = static::createClient();
        $host = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withAddress())->build());
        $anonymous = new AnonymousBeneficiary();
        $anonymous->setEmail('invited@test.local');
        $anonymous->setJoinTo($host->getMainBeneficiary());
        static::persist($anonymous);
        $code = (new SwipeCard($client->getContainer()->getParameter('swipe_card_secret')))->vigenereEncode('invited@test.local');

        $client->request('POST', '/member/add_beneficiary?code=' . urlencode($code), ['form' => ['beneficiary' => [
            'user' => ['email' => 'invited@test.local'],
            'lastname' => 'Invited',
            'firstname' => 'Ivy',
            'phone' => '',
            'address' => ['street1' => '2 rue du Test', 'street2' => '', 'zipcode' => '38000', 'city' => 'Grenoble'],
        ], '_token' => static::csrfToken($client, 'form')]]);

        $this->assertTrue($client->getResponse()->isRedirect('/register/check-email'), json_encode(static::flashes($client)) . $client->getResponse()->getStatusCode());
        $reloaded = static::reloaded($host);
        $this->assertCount(2, $reloaded->getBeneficiaries());
        $this->assertNull(static::entityManager()->getRepository(AnonymousBeneficiary::class)->findOneBy(['email' => 'invited@test.local']), 'The invitation is consumed.');
    }

    // --- detach -------------------------------------------------------------

    public function testAnAdminDetachesABeneficiaryWhichGetsItsOwnMembership(): void
    {
        $client = static::createClient();
        $membership = $this->aMembershipOfTwo();
        $second = $this->secondBeneficiaryOf($membership);
        $secondId = $second->getId();
        static::logIn($client, $this->anAdmin());

        $client->request('POST', sprintf('/beneficiary/%d/detach', $secondId), ['form' => ['_token' => static::csrfToken($client, 'form')]]);

        $this->assertSame(['Le bénéficiaire a été détaché ! Il a maintenant son propre compte.'], static::flashes($client)['success'] ?? []);
        $detached = static::entityManager()->find(Beneficiary::class, $secondId);
        $this->assertNotSame($membership->getId(), $detached->getMembership()->getId());
        $this->assertSame($detached->getId(), $detached->getMembership()->getMainBeneficiary()->getId());
        $this->assertCount(1, static::reloaded($membership)->getBeneficiaries());
    }

    public function testTheMainBeneficiaryCannotBeDetached(): void
    {
        $client = static::createClient();
        $membership = $this->aMembershipOfTwo();
        static::logIn($client, $this->anAdmin());

        $client->request('POST', sprintf('/beneficiary/%d/detach', $membership->getMainBeneficiary()->getId()), ['form' => ['_token' => static::csrfToken($client, 'form')]]);

        $this->assertSame(['Un bénéficiaire principal ne peut pas être détaché'], static::flashes($client)['error'] ?? []);
        $this->assertCount(2, static::reloaded($membership)->getBeneficiaries());
    }

    public function testAMemberCannotDetachTheBeneficiaryOfAnotherMembership(): void
    {
        $client = static::createClient();
        $me = static::aMembership();
        $other = $this->aMembershipOfTwo();
        $second = $this->secondBeneficiaryOf($other);
        static::logIn($client, $me->getMainBeneficiary()->getUser());

        $client->request('POST', sprintf('/beneficiary/%d/detach', $second->getId()), ['form' => ['_token' => static::csrfToken($client, 'form')]]);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertCount(2, static::reloaded($other)->getBeneficiaries());
    }

    public function testAMemberCanDetachTheirOwnSecondBeneficiary(): void
    {
        $client = static::createClient();
        $membership = $this->aMembershipOfTwo();
        $second = $this->secondBeneficiaryOf($membership);
        static::logIn($client, $membership->getMainBeneficiary()->getUser());

        $client->request('POST', sprintf('/beneficiary/%d/detach', $second->getId()), ['form' => ['_token' => static::csrfToken($client, 'form')]]);

        $this->assertCount(1, static::reloaded($membership)->getBeneficiaries());
    }

    // --- join two memberships ----------------------------------------------

    public function testAnAdminJoinsTwoMemberships(): void
    {
        $client = static::createClient();
        $from = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Joining', 'Member')->withAddress())->build());
        $dest = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Hosting', 'Member')->withAddress())->build());
        $fromId = $from->getId();
        $joining = $from->getMainBeneficiary()->getId();
        static::logIn($client, $this->anAdmin());

        $this->postJoin($client, $from, $dest);

        $this->assertSame(['Les deux comptes adhérents ont bien été fusionnés !'], static::flashes($client)['success'] ?? [], json_encode(static::flashes($client)));
        $this->assertNull(static::entityManager()->getRepository(Membership::class)->find($fromId), 'The joined account disappears.');
        $this->assertSame($dest->getId(), static::entityManager()->find(Beneficiary::class, $joining)->getMembership()->getId());
        $this->assertCount(2, static::reloaded($dest)->getBeneficiaries());
    }

    public function testJoiningAMembershipToItselfIsRefused(): void
    {
        $client = static::createClient();
        $member = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Same', 'Member')->withAddress())->build());
        static::logIn($client, $this->anAdmin());

        $this->postJoin($client, $member, $member);

        // The page is rendered in the same request: the flash is in the HTML, not left in the session.
        $this->assertStringContainsString('Impossible de joindre deux comptes identiques.', $client->getResponse()->getContent());
        $this->assertNotNull(static::reloaded($member));
    }

    public function testJoiningPastTheMaximumOfBeneficiariesIsRefused(): void
    {
        $client = static::createClient();
        $from = $this->aMembershipOfTwo('Full');
        $dest = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Hosting', 'Member')->withAddress())->build());
        static::logIn($client, $this->anAdmin());

        $this->postJoin($client, $from, $dest);

        $this->assertStringContainsString('Le compte à lier a déjà le nombre maximum de bénéficiaires.', $client->getResponse()->getContent());
        $this->assertCount(1, static::reloaded($dest)->getBeneficiaries());
    }

    public function testAUserManagerCannotJoinMemberships(): void
    {
        $client = static::createClient();
        $from = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Joining', 'Member')->withAddress())->build());
        $dest = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Hosting', 'Member')->withAddress())->build());
        $fromId = $from->getId();
        static::logIn($client, $this->aUserWithRoles('ROLE_USER_MANAGER'));

        $this->postJoin($client, $from, $dest);

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $this->assertNotNull(static::entityManager()->getRepository(Membership::class)->find($fromId));
    }

    // --- helpers -----------------------------------------------------------

    private function aMembershipOfTwo(string $secondName = 'Second'): Membership
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Main', 'Beneficiary')->withAddress())
                ->withBeneficiary(BeneficiaryBuilder::aBeneficiary()->named($secondName, 'Beneficiary')->withAddress())
                ->build()
        );
    }

    private function secondBeneficiaryOf(Membership $membership): Beneficiary
    {
        return $membership->getBeneficiaries()->filter(function (Beneficiary $b) use ($membership) {
            return $b->getId() !== $membership->getMainBeneficiary()->getId();
        })->first();
    }

    private function anAdmin(): User
    {
        return $this->aUserWithRoles('ROLE_ADMIN');
    }

    private function aUserWithRoles(string ...$roles): User
    {
        return static::persist(
            MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->withAddress()->withUser(UserBuilder::aUser()->withRoles(...$roles)))
                ->build()
        )->getMainBeneficiary()->getUser();
    }

    private function postNewBeneficiary($client, Membership $membership, string $firstname, string $email): void
    {
        $client->request('POST', sprintf('/member/%d/newBeneficiary', $membership->getMemberNumber()), ['App_beneficiary' => [
            'user' => ['email' => $email],
            'lastname' => 'Newcomer',
            'firstname' => $firstname,
            'phone' => '',
            'address' => ['street1' => '2 rue du Test', 'street2' => '', 'zipcode' => '38000', 'city' => 'Grenoble'],
            'flying' => '0',
            '_token' => static::csrfToken($client, 'App_beneficiary'),
        ]]);
    }

    private function postJoin($client, Membership $from, Membership $dest): void
    {
        $label = function (Membership $m) {
            $b = static::entityManager()->find(Beneficiary::class, $m->getMainBeneficiary()->getId());

            return sprintf('#%d %s %s', $b->getMemberNumber(), $b->getFirstname(), $b->getLastname());
        };
        $client->request('POST', '/member/join', ['form' => [
            'from_text' => $label($from),
            'dest_text' => $label($dest),
            'join' => '',
            '_token' => static::csrfToken($client, 'form'),
        ]]);
    }
}
