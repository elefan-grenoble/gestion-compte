<?php

namespace App\Tests\Functional\Controller;

use App\Entity\HelloassoPayment;
use App\Entity\Membership;
use App\Helper\SwipeCard;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * Resolving an orphan HelloAsso payment from the link mailed to its payer
 * (helloasso_confirm_resolve_orphan).
 *
 * @internal
 */
class HelloassoOrphanTest extends FunctionalTestCase
{
    use ShiftScenarios;

    /**
     * A payment that is already linked to a registration is not linked a
     * second time (I-BUG-7: the confirmation dispatched ORPHAN_SOLVE without
     * checking, the GET link could be replayed).
     */
    public function testConfirmingAPaymentAlreadyLinkedChangesNothing(): void
    {
        $client = static::createClient();
        $owner = static::persist(MembershipBuilder::aMembership()->registeredOn(new \DateTime('-1 month'))->build());
        $payment = $this->aPaymentOf($owner, 'payer@example.org');
        $other = static::persist(MembershipBuilder::aMembership()->registeredOn()->build());
        static::logIn($client, $other->getMainBeneficiary()->getUser());

        $code = urlencode(static::$container->get(SwipeCard::class)->vigenereEncode('payer@example.org'));
        $client->request('GET', sprintf('/helloasso/payment/%d/confirm_resolve_orphan/%s', $payment->getId(), $code));

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertArrayHasKey('error', static::flashes($client));
        $this->assertSame($owner->getLastRegistration()->getId(), static::reloaded($payment)->getRegistration()->getId());
        $this->assertCount(0, static::reloaded($other)->getRegistrations());
    }

    private function aPaymentOf(Membership $membership, string $email): HelloassoPayment
    {
        $payment = new HelloassoPayment();
        $payment->setPaymentId(random_int(1, 1000000000));
        $payment->setDate(new \DateTime('-1 month'));
        $payment->setAmount(10.0);
        $payment->setEmail($email);
        $payment->setPayerFirstName('Paula');
        $payment->setPayerLastName('Payer');
        $payment->setStatus('Authorized');
        $payment->setRegistration($membership->getLastRegistration());

        return static::persist($payment);
    }
}
