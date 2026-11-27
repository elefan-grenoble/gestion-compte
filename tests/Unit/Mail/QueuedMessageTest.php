<?php

namespace App\Tests\Unit\Mail;

use App\Mail\QueuedMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * A mail waiting in the queue is stored as JSON and rebuilt to be sent.
 *
 * @internal
 */
class QueuedMessageTest extends TestCase
{
    public function testAMailComesBackAsItWasWritten(): void
    {
        $email = (new Email())
            ->from(new Address('sender@example.org', 'La coop'))
            ->to(new Address('first@example.org', 'Éloïse Durand'), 'second@example.org')
            ->cc('copy@example.org')
            ->replyTo('reply@example.org')
            ->subject('Réunion générale — jeudi')
            ->html('<p>Bonjour « Éloïse »</p>')
            ->text('Bonjour')
        ;
        $email->getHeaders()->addIdHeader('Message-ID', 'abc123@example.org');

        $copy = QueuedMessage::decode(QueuedMessage::encode($email));

        $this->assertSame('La coop', $copy->getFrom()[0]->getName());
        $this->assertSame('sender@example.org', $copy->getFrom()[0]->getAddress());
        $this->assertSame(['first@example.org', 'second@example.org'], array_map(static function (Address $a) {
            return $a->getAddress();
        }, $copy->getTo()));
        $this->assertSame('Éloïse Durand', $copy->getTo()[0]->getName());
        $this->assertSame('copy@example.org', $copy->getCc()[0]->getAddress());
        $this->assertSame('reply@example.org', $copy->getReplyTo()[0]->getAddress());
        $this->assertSame('Réunion générale — jeudi', $copy->getSubject());
        $this->assertSame('<p>Bonjour « Éloïse »</p>', $copy->getHtmlBody());
        $this->assertSame('Bonjour', $copy->getTextBody());
        $this->assertSame('<abc123@example.org>', $copy->getHeaders()->get('Message-ID')->getBodyAsString());
    }

    public function testAMailWithAttachmentsCannotWaitInTheQueue(): void
    {
        $email = (new Email())->from('a@example.org')->to('b@example.org')->subject('x')->html('y')->attach('data', 'file.txt', 'text/plain');

        $this->assertNull(QueuedMessage::encode($email));
    }

    /**
     * @dataProvider unreadableProvider
     */
    public function testAnUnreadableMailIsRefused(string $json): void
    {
        $this->expectException(\InvalidArgumentException::class);

        QueuedMessage::decode($json);
    }

    public function unreadableProvider(): array
    {
        return [
            'not json' => ['{oops'],
            'no sender' => ['{"from":[],"to":[["b@example.org",""]],"subject":"x"}'],
            'no recipient' => ['{"from":[["a@example.org",""]],"to":[],"subject":"x"}'],
        ];
    }
}
