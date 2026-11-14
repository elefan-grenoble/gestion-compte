<?php

namespace App\Tests\Functional\Controller;

use App\Entity\SentEmail;
use App\Mail\MailQueueStatus;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Support\Builder\BeneficiaryBuilder;
use App\Tests\Support\Builder\MembershipBuilder;

/**
 * The admin page "send a mail" (POST /admin/mail/send): sent at once, or put in
 * the queue of the background sending when MAIL_QUEUE_ENABLED is on and the
 * crontab runs the command that sends the queue.
 *
 * @internal
 */
class MailControllerSendTest extends FunctionalTestCase
{
    /** @var null|false|string */
    private $originalQueueSetting;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::loadFixtures(['period']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalQueueSetting = $_ENV['MAIL_QUEUE_ENABLED'] ?? getenv('MAIL_QUEUE_ENABLED');
    }

    protected function tearDown(): void
    {
        $this->queueEnabled(false === $this->originalQueueSetting || null === $this->originalQueueSetting ? null : (string) $this->originalQueueSetting);
        $this->queueStatus()->reset();
        parent::tearDown();
    }

    public function testWithoutTheQueueTheMailsAreSentDuringTheRequest(): void
    {
        $this->queueEnabled('0');
        $client = $this->loginAs('admin');

        $this->send($client, ['Alice Queue', 'Bruno Queue']);

        $this->assertSame([SentEmail::STATUS_SENT, SentEmail::STATUS_SENT], $this->statuses('Hello queue'));
        $flashes = $client->getContainer()->get('session')->getFlashBag()->peekAll();
        $this->assertSame(['2 messages envoyés'], $flashes['success']);
    }

    public function testWithTheQueueTheMailsWaitInTheHistoryAndTheAnswerIsImmediate(): void
    {
        $this->queueEnabled('1');
        $client = $this->loginAs('admin');
        $this->queueStatus()->touch();

        $this->send($client, ['Chloé Queue', 'David Queue', 'Emma Queue']);

        $this->assertSame([SentEmail::STATUS_QUEUED, SentEmail::STATUS_QUEUED, SentEmail::STATUS_QUEUED], $this->statuses('Hello queue'));
        $flashes = $client->getContainer()->get('session')->getFlashBag()->peekAll();
        $this->assertStringContainsString('3 messages en file d\'envoi', $flashes['success'][0]);
        $this->assertStringContainsString('<a href="/admin/mails/historique/"', $flashes['success'][0], 'The history is one click away.');
        $this->assertTrue($client->getResponse()->isRedirect('/admin/mail/'));

        $batchIds = array_unique(static::$container->get('doctrine')->getConnection()->fetchFirstColumn("SELECT batch_id FROM sent_email WHERE subject = 'Hello queue'"));
        $this->assertCount(1, $batchIds, 'The mails of one sending form one group.');
    }

    public function testWithoutTheCrontabTheMailsAreSentAtOnceWhateverTheEnvironmentSays(): void
    {
        $this->queueEnabled('1');
        $client = $this->loginAs('admin');
        $this->queueStatus()->reset();

        $this->send($client, ['Karine Queue', 'Louis Queue']);

        $this->assertSame([SentEmail::STATUS_SENT, SentEmail::STATUS_SENT], $this->statuses('Hello queue'), 'No run of app:mail:send-pending: nobody would send the queue.');
        $flashes = $client->getContainer()->get('session')->getFlashBag()->peekAll();
        $this->assertSame(['2 messages envoyés'], $flashes['success']);
    }

    /**
     * @param string[]             $names  first names of the recipients (members created for the test)
     * @param array<string, mixed> $fields fields of the form to change (from, subject, message, cci)
     * @param mixed                $client
     */
    private function send($client, array $names, array $fields = [])
    {
        static::$container->get('doctrine')->getConnection()->executeStatement("DELETE FROM sent_email WHERE subject = 'Hello queue'");
        $to = [];
        foreach (array_values($names) as $i => $name) {
            [$first, $last] = explode(' ', $name);
            // The form validates the beneficiaries it is given: they need an address.
            $membership = MembershipBuilder::aMembership()
                ->withMainBeneficiary(BeneficiaryBuilder::aBeneficiary()->named($first, $last)->withAddress())
                ->build()
            ;
            static::persist($membership);
            // The form finds a beneficiary from this text, as the page builds it.
            $to[$i] = static::$container->get('doctrine')->getConnection()->fetchOne(
                "SELECT CONCAT('#', m.member_number, ' ', b.firstname, ' ', b.lastname) FROM beneficiary b JOIN membership m ON m.id = b.membership_id WHERE b.id = ?",
                [$membership->getMainBeneficiary()->getId()]
            );
        }

        $crawler = $client->request('GET', '/admin/mail/');
        $token = $crawler->filterXPath('//input[@name="form[_token]"]')->attr('value');
        $from = $crawler->filterXPath('//select[@name="form[from]"]/option[normalize-space(@value) != ""]')->first()->attr('value');

        $form = $fields + [
            'from' => $from,
            'to' => $to,
            'cci' => '[]',
            'subject' => 'Hello queue',
            'message' => 'Bonjour {{ beneficiary.firstname }}',
            '_token' => $token,
        ];

        return $client->request('POST', '/admin/mail/send', ['form' => $form]);
    }

    /**
     * @return string[]
     */
    private function statuses(string $subject): array
    {
        return static::$container->get('doctrine')->getConnection()->fetchFirstColumn('SELECT status FROM sent_email WHERE subject = ? ORDER BY id', [$subject]);
    }

    private function queueStatus(): MailQueueStatus
    {
        if (null === static::$container) {
            static::bootKernel();
        }

        return static::$container->get(MailQueueStatus::class);
    }

    private function queueEnabled(?string $value): void
    {
        if (null === $value) {
            unset($_ENV['MAIL_QUEUE_ENABLED'], $_SERVER['MAIL_QUEUE_ENABLED']);
            putenv('MAIL_QUEUE_ENABLED');

            return;
        }
        $_ENV['MAIL_QUEUE_ENABLED'] = $_SERVER['MAIL_QUEUE_ENABLED'] = $value;
        putenv('MAIL_QUEUE_ENABLED=' . $value);
    }
}
