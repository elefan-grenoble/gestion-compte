<?php

namespace App\Tests\Integration;

use App\Entity\HelloassoPayment;
use App\Event\HelloassoEvent;
use App\Tests\Support\PersistsEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/**
 * @internal
 */
class HelloassoEventListenerTest extends KernelTestCase
{
    use MailerAssertionsTrait;
    use PersistsEntities;

    protected function setUp(): void
    {
        static::bootKernel();
    }

    /**
     * A payment whose email matches no account asks its payer who they are,
     * with a coded link (MAIL-SWIPECARD-DI: the helper was fetched from the
     * container, which does not expose it).
     */
    public function testAnOrphanPaymentAsksThePayerWhoTheyAre(): void
    {
        $payment = new HelloassoPayment();
        $payment->setPaymentId(random_int(1, 1000000000));
        $payment->setDate(new \DateTime('-1 day'));
        $payment->setAmount(10.0);
        $payment->setEmail('unknown-payer@example.org');
        $payment->setPayerFirstName('Paula');
        $payment->setPayerLastName('Payer');
        $payment->setStatus('Authorized');
        static::persist($payment);

        static::$container->get('event_dispatcher')->dispatch(new HelloassoEvent($payment, null), HelloassoEvent::PAYMENT_AFTER_SAVE);

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', 'unknown-payer@example.org');
        $this->assertEmailHtmlBodyContains($email, '/resolve_orphan/');
    }
}
