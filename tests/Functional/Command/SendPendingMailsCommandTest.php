<?php

namespace App\Tests\Functional\Command;

use App\Entity\SentEmail;
use App\Mail\MailMetadata;
use App\Mail\MailQueueStatus;
use Symfony\Component\Mime\Email;

/**
 * app:mail:send-pending sends the mails that wait in the history (the mails of
 * the admin page "send a mail" when MAIL_QUEUE_ENABLED is on).
 *
 * @internal
 */
class SendPendingMailsCommandTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();
        static::$container->get('doctrine')->getConnection()->executeStatement('DELETE FROM sent_email');
        static::$container->get(MailQueueStatus::class)->reset();
    }

    public function testSendsTheQueuedMailsAndMarksThemSent(): void
    {
        $this->queue('a@example.org', 'b@example.org');
        $this->assertSame([], $this->sentEmails(), 'Nothing leaves while the mails wait.');

        $tester = $this->runCommand('app:mail:send-pending');

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('2 sent, 0 failed, 0 still waiting.', $tester->getDisplay());
        $this->assertSame(['a@example.org', 'b@example.org'], array_map(static function (Email $email) {
            return $email->getTo()[0]->getAddress();
        }, $this->sentEmails()));
        $this->assertSame([SentEmail::STATUS_SENT, SentEmail::STATUS_SENT], $this->statuses());
    }

    public function testItNotesWhenItLastRanSoThatTheQueueIsOnlyUsedWhileItRuns(): void
    {
        $status = static::$container->get(MailQueueStatus::class);
        $this->assertNull($status->lastRun());

        $this->runCommand('app:mail:send-pending');

        $this->assertLessThan(5, abs(time() - $status->lastRun()->getTimestamp()));
        $status->reset();
    }

    private function queue(string ...$recipients): void
    {
        $mailer = static::$container->get('mailer.mailer');
        foreach ($recipients as $to) {
            $mailer->send(MailMetadata::queue(MailMetadata::written(
                (new Email())->from('sender@example.org')->to($to)->subject('Group')->html('<p>Hello</p>'),
                null,
                'queue-command-test'
            )));
        }
    }

    /**
     * @return string[]
     */
    private function statuses(): array
    {
        return static::$container->get('doctrine')->getConnection()->fetchFirstColumn('SELECT status FROM sent_email ORDER BY id');
    }
}
