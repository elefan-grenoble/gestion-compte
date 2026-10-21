<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Beneficiary;
use App\Entity\Shift;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;
use App\Tests\Support\Builder\ShiftBuilder;
use App\Tests\Support\ShiftScenarios;

/**
 * The form with which a shifter mails the co-shifters of a shift
 * (shift_contact_form). Anonymous access is pinned by ShiftSecurityTest
 * (I-SEC-2).
 *
 * @internal
 */
class ShiftControllerContactFormTest extends FunctionalTestCase
{
    use ShiftScenarios;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    public function testTheShifterSeesTheFormWithTheCoShifters(): void
    {
        $client = static::createClient();
        [$shift, $shifter, $coShifter] = $this->aShiftWithACoShifter();
        static::logIn($client, $shifter->getUser());

        $crawler = $client->request('GET', '/shift/' . $shift->getId() . '/contact_form');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertCount(1, $crawler->filterXPath('//form[substring(@action, string-length(@action) - ' . (strlen('/contact_form') + strlen((string) $shift->getId())) . ') = "/' . $shift->getId() . '/contact_form"]'));
        $recipients = implode('|', $crawler->filterXPath('//input[@type="hidden"]')->extract(['value']));
        $this->assertStringContainsString('Bob', $recipients, 'The co-shifter is offered as a recipient.');
    }

    public function testSendingMailsTheCoShiftersAndRedirects(): void
    {
        $client = static::createClient();
        [$shift, $shifter, $coShifter] = $this->aShiftWithACoShifter();
        static::logIn($client, $shifter->getUser());
        $client->enableProfiler();

        $this->postContact($client, $shift, [$coShifter], 'I will be late');

        $this->assertTrue($client->getResponse()->isRedirect('/'));
        $this->assertSame(['Ton message a été transmis à ' . $coShifter->getFirstname()], static::flashes($client)['success'] ?? []);

        $emails = $client->getProfile()->getCollector('mailer')->getEvents()->getMessages();
        $this->assertCount(1, $emails);
        $this->assertSame([$coShifter->getEmail()], array_map(function ($a) {
            return $a->getAddress();
        }, $emails[0]->getBcc()));
        $this->assertSame($shifter->getEmail(), $emails[0]->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('I will be late', $emails[0]->getHtmlBody());
    }

    /**
     * The sender is the logged-in member, who must belong to the membership
     * holding the shift (SHIFT-CONTACT-FROM: the sender was a hidden field,
     * so any member could mail the co-shifters in the name of the shifter).
     */
    public function testAMemberCannotMailTheCoShiftersInTheNameOfTheShifter(): void
    {
        $client = static::createClient();
        [$shift, $shifter, $coShifter] = $this->aShiftWithACoShifter();
        $intruder = static::persist(MembershipBuilder::aMembership()->build())->getMainBeneficiary();
        static::logIn($client, $intruder->getUser());
        $client->enableProfiler();

        $this->postContact($client, $shift, [$coShifter], 'Send me your password');

        $this->assertSame(403, $client->getResponse()->getStatusCode());
        $profile = $client->getProfile();
        $sent = $profile ? count($profile->getCollector('mailer')->getEvents()->getMessages()) : 0;
        $this->assertSame(0, $sent, 'A mail was sent in the name of somebody else.');
    }

    /**
     * A shift nobody holds has no contact form (SHIFT-CONTACT-FREE: reading
     * its shifter to prefill the form crashed).
     */
    public function testTheFormOfAFreeShiftDoesNotCrash(): void
    {
        $client = static::createClient();
        $member = static::aMembership()->getMainBeneficiary();
        $shift = static::aBookableShift(new \DateTime('+2 days 09:00'));
        static::logIn($client, $member->getUser());

        $client->request('GET', '/shift/' . $shift->getId() . '/contact_form');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    private function aShiftWithACoShifter(): array
    {
        $shifter = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Alice', 'Shifter')->withAddress())->build())->getMainBeneficiary();
        $coShifter = static::persist(MembershipBuilder::aMembership()->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named('Bob', 'Coshifter')->withAddress())->build())->getMainBeneficiary();
        $start = new \DateTime('+2 days 09:00');
        $mine = ShiftBuilder::aShift()->startingAt($start)->bookedBy($shifter)->build();
        $theirs = ShiftBuilder::aShift()->startingAt($start)->forJob($mine->getJob())->bookedBy($coShifter)->build();
        static::persist($mine->getJob(), $mine, $theirs);
        static::entityManager()->clear();

        return [$mine, $shifter, $coShifter];
    }

    private function postContact($client, Shift $shift, array $to, string $message): void
    {
        $name = 'shift_contact_form_' . $shift->getId();
        $client->request('POST', '/shift/' . $shift->getId() . '/contact_form', [$name => [
            'to' => array_map(function (Beneficiary $b) {
                return sprintf('#%d %s %s', static::reloaded($b)->getMemberNumber(), $b->getFirstname(), $b->getLastname());
            }, $to),
            'message' => $message,
            '_token' => static::csrfToken($client, $name),
        ]]);
    }
}
