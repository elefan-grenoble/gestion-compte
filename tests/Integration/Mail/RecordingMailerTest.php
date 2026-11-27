<?php

namespace App\Tests\Integration\Mail;

use App\Entity\SentEmail;
use App\Mail\MailMetadata;
use App\Mail\QueuedMessage;
use App\Mail\RecordingMailer;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * The history of the sent mails.
 *
 * @internal
 */
class RecordingMailerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        static::bootKernel();
    }

    public function testTheMailerOfTheApplicationRecordsEveryMail(): void
    {
        $mailer = static::$container->get('mailer.mailer');
        $this->assertInstanceOf(RecordingMailer::class, $mailer);

        $mailer->send((new Email())
            ->from('sender@example.org')
            ->to('first@example.org')
            ->cc('second@example.org')
            ->subject('Hello there')
            ->html('<p>Body</p>'));

        $row = $this->lastRow();
        $this->assertSame(SentEmail::STATUS_SENT, $row['status']);
        $this->assertSame(SentEmail::SOURCE_AUTO, $row['source']);
        $this->assertSame('sender@example.org', $row['from_address']);
        $this->assertSame('first@example.org, second@example.org', $row['recipients']);
        $this->assertSame('2', (string) $row['recipient_count']);
        $this->assertSame('Hello there', $row['subject']);
        $this->assertNull($row['body'], 'The body is only kept for the mails written by a person.');
        $this->assertNotEmpty($row['message_id']);
    }

    public function testAMailWrittenByAPersonKeepsItsBody(): void
    {
        $email = (new Email())->from('sender@example.org')->to('a@example.org')->subject('Written')->html('<p>Dear member</p>');
        MailMetadata::written($email);

        static::$container->get('mailer.mailer')->send($email);

        $row = $this->lastRow();
        $this->assertSame(SentEmail::SOURCE_MANUAL, $row['source']);
        $this->assertSame('<p>Dear member</p>', $row['body']);
    }

    public function testEveryMailOfAGroupHasItsLineAndTheLinesShareTheBatch(): void
    {
        $mailer = static::$container->get('mailer.mailer');

        foreach (['a@example.org', 'b@example.org', 'c@example.org'] as $to) {
            $mailer->send(MailMetadata::written((new Email())->from('sender@example.org')->to($to)->subject('Hello all')->html('<p>Dear ' . $to . '</p>'), null, 'group-1'));
        }
        $mailer->send(MailMetadata::written((new Email())->from('sender@example.org')->to('d@example.org')->subject('Other')->html('x'), null, 'group-2'));

        $rows = $this->connection()->fetchAllAssociative("SELECT * FROM sent_email WHERE batch_id = 'group-1' ORDER BY id");
        $this->assertCount(3, $rows);
        $this->assertSame(['a@example.org', 'b@example.org', 'c@example.org'], array_column($rows, 'recipients'));
        $this->assertSame('<p>Dear b@example.org</p>', $rows[1]['body']);
        $this->assertCount(3, array_unique(array_column($rows, 'message_id')), 'Each mail keeps its own Message-ID.');
        $this->assertSame([SentEmail::STATUS_SENT], array_unique(array_column($rows, 'status')));
    }

    public function testTheInternalHeadersNeverLeave(): void
    {
        $inner = $this->aMailerThatKeepsTheMessage();
        $email = (new Email())->from('sender@example.org')->to('a@example.org')->subject('Written')->html('x');
        MailMetadata::written($email, null, 'batch-1');

        (new RecordingMailer($inner, $this->connection(), new NullLogger()))->send($email);

        foreach (MailMetadata::HEADERS as $name) {
            $this->assertFalse($inner->message->getHeaders()->has($name), $name . ' must be removed before the mail is sent.');
        }
        $this->assertTrue($inner->message->getHeaders()->has('Message-ID'));
    }

    public function testAMailRefusedByTheServerIsRecordedAsFailedAndTheErrorIsRaisedAgain(): void
    {
        $inner = new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('550 Mailbox unavailable');
            }
        };
        $mailer = new RecordingMailer($inner, $this->connection(), new NullLogger());

        try {
            $mailer->send((new Email())->from('sender@example.org')->to('gone@example.org')->subject('Refused')->html('x'));
            $this->fail('The transport error should reach the caller.');
        } catch (TransportException $e) {
            $this->assertSame('550 Mailbox unavailable', $e->getMessage());
        }

        $row = $this->lastRow();
        $this->assertSame(SentEmail::STATUS_FAILED, $row['status']);
        $this->assertSame('550 Mailbox unavailable', $row['error']);
    }

    public function testAMailIsSentEvenWhenTheHistoryCannotBeWritten(): void
    {
        $inner = $this->aMailerThatKeepsTheMessage();
        $brokenConnection = $this->createMock(Connection::class);
        $brokenConnection->method('insert')->willThrowException(new \RuntimeException('no table'));

        (new RecordingMailer($inner, $brokenConnection, new NullLogger()))
            ->send((new Email())->from('sender@example.org')->to('a@example.org')->subject('Still sent')->html('x'))
        ;

        $this->assertNotNull($inner->message, 'The mail must be sent whatever happens to the history.');
    }

    public function testAQueuedMailWaitsInTheHistoryInsteadOfBeingSent(): void
    {
        $inner = $this->aMailerThatKeepsTheMessage();
        $email = MailMetadata::queue(MailMetadata::written(
            (new Email())->from('sender@example.org')->to('a@example.org')->subject('Later')->html('<p>Dear member</p>'),
            null,
            'group-queue'
        ));

        (new RecordingMailer($inner, $this->connection(), new NullLogger()))->send($email);

        $this->assertNull($inner->message, 'A queued mail is not handed to the mail server at once.');
        $row = $this->lastRow();
        $this->assertSame(SentEmail::STATUS_QUEUED, $row['status']);
        $this->assertSame('a@example.org', $row['recipients']);
        $this->assertSame('<p>Dear member</p>', $row['body']);
        $this->assertSame('group-queue', $row['batch_id']);
        $this->assertNotEmpty($row['message_id']);
        $queued = QueuedMessage::decode($row['queued_message']);
        $this->assertSame('Later', $queued->getSubject());
        $this->assertSame('<' . $row['message_id'] . '>', $queued->getHeaders()->get('Message-ID')->getBodyAsString());
        foreach (MailMetadata::HEADERS as $name) {
            $this->assertFalse($queued->getHeaders()->has($name), $name . ' must not be kept in the queue.');
        }
    }

    public function testAQueuedMailThatCannotWaitIsSentAtOnce(): void
    {
        $inner = $this->aMailerThatKeepsTheMessage();
        $email = MailMetadata::queue((new Email())->from('sender@example.org')->to('a@example.org')->subject('With file')->html('x')->attach('data', 'file.txt', 'text/plain'));

        (new RecordingMailer($inner, $this->connection(), new NullLogger()))->send($email);

        $this->assertNotNull($inner->message);
        $row = $this->lastRow();
        $this->assertSame(SentEmail::STATUS_SENT, $row['status']);
        $this->assertNull($row['queued_message']);
    }

    public function testAQueuedMailIsSentAtOnceWhenTheHistoryCannotBeWritten(): void
    {
        $inner = $this->aMailerThatKeepsTheMessage();
        $brokenConnection = $this->createMock(Connection::class);
        $brokenConnection->method('insert')->willThrowException(new \RuntimeException('no table'));

        (new RecordingMailer($inner, $brokenConnection, new NullLogger()))
            ->send(MailMetadata::queue((new Email())->from('sender@example.org')->to('a@example.org')->subject('Not lost')->html('x')))
        ;

        $this->assertNotNull($inner->message, 'A mail must not be lost when it cannot be put in the queue.');
    }

    private function connection(): Connection
    {
        return static::$container->get('doctrine')->getConnection();
    }

    /**
     * @return array<string, mixed>
     */
    private function lastRow(): array
    {
        $row = $this->connection()->fetchAssociative('SELECT * FROM sent_email ORDER BY id DESC LIMIT 1');
        $this->assertIsArray($row, 'The mail was not recorded.');

        return $row;
    }

    private function aMailerThatKeepsTheMessage(): MailerInterface
    {
        return new class implements MailerInterface {
            /** @var null|RawMessage */
            public $message;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->message = $message;
            }
        };
    }
}
