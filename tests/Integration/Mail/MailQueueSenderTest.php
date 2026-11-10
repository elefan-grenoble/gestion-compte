<?php

namespace App\Tests\Integration\Mail;

use App\Entity\SentEmail;
use App\Mail\MailMetadata;
use App\Mail\MailQueueSender;
use App\Mail\RecordingMailer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * The background sending of the mails waiting in the history.
 *
 * @internal
 */
class MailQueueSenderTest extends KernelTestCase
{
    /** @var string[] */
    private $mailsSent = [];

    protected function setUp(): void
    {
        static::bootKernel();
        $this->connection()->executeStatement("DELETE FROM sent_email WHERE batch_id LIKE 'queue-test-%'");
        $this->mailsSent = [];
    }

    protected function tearDown(): void
    {
        $this->connection()->executeStatement("DELETE FROM sent_email WHERE batch_id LIKE 'queue-test-%'");
        parent::tearDown();
    }

    public function testTheQueuedMailsAreSentOldestFirstAndMarkedSent(): void
    {
        $this->queue('a@example.org', 'b@example.org', 'c@example.org');

        $result = $this->sender()->send();

        $this->assertSame(['sent' => 3, 'failed' => 0, 'remaining' => $this->waiting()], $result);
        $this->assertSame(['a@example.org', 'b@example.org', 'c@example.org'], $this->mailsSent);
        $rows = $this->rows();
        $this->assertSame([SentEmail::STATUS_SENT], array_unique(array_column($rows, 'status')));
        $this->assertSame([null], array_unique(array_column($rows, 'queued_message')), 'The mail is forgotten once it has been sent.');
        $this->assertSame([null], array_unique(array_column($rows, 'error')));
    }

    public function testAMailRefusedByTheServerIsFailedAndTheOthersStillGo(): void
    {
        $this->queue('a@example.org', 'bad@example.org', 'c@example.org');

        $result = $this->sender(static function (Email $email) {
            if ('bad@example.org' === $email->getTo()[0]->getAddress()) {
                throw new TransportException('550 Mailbox unavailable');
            }
        })->send();

        $this->assertSame(2, $result['sent']);
        $this->assertSame(1, $result['failed']);
        $rows = $this->rows();
        $this->assertSame([SentEmail::STATUS_SENT, SentEmail::STATUS_FAILED, SentEmail::STATUS_SENT], array_column($rows, 'status'));
        $this->assertSame('550 Mailbox unavailable', $rows[1]['error']);
        $this->assertNull($rows[1]['queued_message']);
    }

    public function testTheLimitLeavesTheRestForTheNextRun(): void
    {
        $this->queue('a@example.org', 'b@example.org', 'c@example.org');
        $before = $this->waiting() - 3;

        $first = $this->sender()->send(2);
        $this->assertSame(2, $first['sent']);
        $this->assertSame($before + 1, $first['remaining']);

        $second = $this->sender()->send(2);
        $this->assertSame(1, $second['sent']);
        $this->assertSame(['a@example.org', 'b@example.org', 'c@example.org'], $this->mailsSent);
    }

    public function testAnUnreadableQueuedMailIsFailedWithoutStoppingTheOthers(): void
    {
        $this->queue('a@example.org', 'b@example.org');
        $this->connection()->update('sent_email', ['queued_message' => '{broken'], ['id' => $this->rows()[0]['id']]);

        $result = $this->sender()->send();

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame([SentEmail::STATUS_FAILED, SentEmail::STATUS_SENT], array_column($this->rows(), 'status'));
    }

    public function testOnlyOneSenderWorksAtATime(): void
    {
        $this->queue('a@example.org');
        $other = DriverManager::getConnection($this->connection()->getParams());
        $this->assertSame(1, (int) $other->fetchOne("SELECT GET_LOCK('app_mail_queue', 0)"));

        try {
            $this->assertNull($this->sender()->send(), 'A second run must leave the mails to the one that is working.');
            $this->assertSame([], $this->mailsSent);
        } finally {
            $other->fetchOne("SELECT RELEASE_LOCK('app_mail_queue')");
        }

        $this->assertNotNull($this->sender()->send());
        $this->assertSame(['a@example.org'], $this->mailsSent);
    }

    public function testTheSenderOfTheApplicationIsWiredToTheRealMailer(): void
    {
        $this->assertInstanceOf(MailQueueSender::class, static::$container->get(MailQueueSender::class));
    }

    private function queue(string ...$recipients): void
    {
        // A recorder over a mailer that is never called puts the mails in the queue.
        $recorder = new RecordingMailer($this->mailer(), $this->connection(), new NullLogger());
        foreach ($recipients as $to) {
            $recorder->send(MailMetadata::queue(MailMetadata::written(
                (new Email())->from('sender@example.org')->to($to)->subject('Group')->html('<p>' . $to . '</p>'),
                null,
                'queue-test-1'
            )));
        }
    }

    private function sender(?callable $onSend = null): MailQueueSender
    {
        return new MailQueueSender($this->mailer($onSend), $this->connection(), new NullLogger());
    }

    private function mailer(?callable $onSend = null): MailerInterface
    {
        return new class ($onSend, $this->mailsSent) implements MailerInterface {
            private $onSend;
            private $sent;

            public function __construct(?callable $onSend, array &$sent)
            {
                $this->onSend = $onSend;
                $this->sent = &$sent;
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if ($this->onSend) {
                    ($this->onSend)($message);
                }
                $this->sent[] = $message->getTo()[0]->getAddress();
            }
        };
    }

    private function connection(): Connection
    {
        return static::$container->get('doctrine')->getConnection();
    }

    private function waiting(): int
    {
        return (int) $this->connection()->fetchOne("SELECT COUNT(id) FROM sent_email WHERE status = 'queued'");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->connection()->fetchAllAssociative("SELECT * FROM sent_email WHERE batch_id = 'queue-test-1' ORDER BY id");
    }
}
