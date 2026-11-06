<?php

namespace App\Mail;

use App\Entity\SentEmail;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Wraps the mailer of the application to write every mail in the history
 * (sent_email table): automatic mails (reminders, account mails...) and mails written by admins.
 *
 * The status only reflects what the SMTP server answered: "sent" means that
 * the server accepted the mail, "failed" that it (or the connection) refused.
 *
 * The history goes through the DBAL connection, not the entity manager: a
 * flush here would also write whatever the caller has not finished with. A
 * failure of the history never prevents a mail from being sent.
 */
class RecordingMailer implements MailerInterface
{
    /** @var MailerInterface */
    private $inner;

    /** @var Connection */
    private $connection;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(MailerInterface $inner, Connection $connection, LoggerInterface $logger)
    {
        $this->inner = $inner;
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if (!$message instanceof Email) {
            $this->inner->send($message, $envelope);

            return;
        }

        $queued = $message->getHeaders()->has(MailMetadata::HEADER_QUEUE) && null === $envelope;
        $id = $this->open($message, $envelope, $queued);
        if ($queued) {
            // Waiting in the history: MailQueueSender sends it later on.
            return;
        }

        try {
            $this->inner->send($message, $envelope);
        } catch (\Throwable $e) {
            $this->close($id, SentEmail::STATUS_FAILED, $e->getMessage());

            throw $e;
        }

        $this->close($id, SentEmail::STATUS_SENT, null);
    }

    /**
     * Writes the "pending" line of the history.
     *
     * @param bool $queued in: the mail should wait in the queue; out: it does (it
     *                     is sent at once if it cannot wait, e.g. attachments, or
     *                     if the history could not be written)
     *
     * @return null|int id of the line, null when it could not be written
     */
    private function open(Email $message, ?Envelope $envelope, bool &$queued): ?int
    {
        $headers = $message->getHeaders();
        $source = $headers->has(MailMetadata::HEADER_SOURCE) ? $headers->get(MailMetadata::HEADER_SOURCE)->getBodyAsString() : SentEmail::SOURCE_AUTO;
        $storeBody = $headers->has(MailMetadata::HEADER_STORE_BODY);
        $sentBy = $headers->has(MailMetadata::HEADER_SENT_BY) ? (int) $headers->get(MailMetadata::HEADER_SENT_BY)->getBodyAsString() : null;
        $batchId = $headers->has(MailMetadata::HEADER_BATCH) ? $headers->get(MailMetadata::HEADER_BATCH)->getBodyAsString() : null;

        // The internal headers are not for the recipients.
        foreach (MailMetadata::HEADERS as $name) {
            $headers->remove($name);
        }

        $from = $message->getFrom();
        $fromAddress = $from ? $from[0]->getAddress() : ($envelope ? $envelope->getSender()->getAddress() : '');

        $messageId = $this->ensureMessageId($message, $fromAddress);

        $recipients = [];
        foreach (array_merge($message->getTo(), $message->getCc(), $message->getBcc()) as $address) {
            $recipients[] = $address->getAddress();
        }
        if (!$recipients && $envelope) {
            foreach ($envelope->getRecipients() as $address) {
                $recipients[] = $address->getAddress();
            }
        }

        $body = null;
        if ($storeBody) {
            $body = $message->getHtmlBody();
            if (null === $body) {
                $body = $message->getTextBody();
            }
            if (is_resource($body)) {
                $body = stream_get_contents($body);
            }
        }

        $queuedMessage = $queued ? QueuedMessage::encode($message) : null;
        $queued = null !== $queuedMessage;
        $status = $queued ? SentEmail::STATUS_QUEUED : SentEmail::STATUS_PENDING;

        try {
            $this->connection->insert('sent_email', [
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'source' => $source,
                'from_address' => mb_substr($fromAddress, 0, 255),
                'recipients' => implode(', ', $recipients),
                'recipient_count' => count($recipients),
                'subject' => mb_substr((string) $message->getSubject(), 0, 255),
                'body' => $body,
                'status' => $status,
                'error' => null,
                'queued_message' => $queuedMessage,
                'message_id' => $messageId,
                'sent_by_id' => $sentBy,
                'batch_id' => $batchId,
            ]);

            return (int) $this->connection->lastInsertId();
        } catch (\Throwable $e) {
            $this->logger->warning('Mail history: the mail could not be recorded: ' . $e->getMessage());
            $queued = false;

            return null;
        }
    }

    private function close(?int $id, string $status, ?string $error): void
    {
        if (null === $id) {
            return;
        }

        try {
            $this->connection->update('sent_email', [
                'status' => $status,
                'error' => $error,
            ], ['id' => $id]);
        } catch (\Throwable $e) {
            $this->logger->warning('Mail history: the status of the mail could not be updated: ' . $e->getMessage());
        }
    }

    /**
     * Gives the mail a Message-ID (the key to match a bounce to its mail later
     * on) unless it already has one.
     */
    private function ensureMessageId(Email $message, string $fromAddress): string
    {
        $headers = $message->getHeaders();
        if ($headers->has('Message-ID')) {
            return trim($headers->get('Message-ID')->getBodyAsString(), '<>');
        }

        $domain = false !== strpos($fromAddress, '@') ? substr(strrchr($fromAddress, '@'), 1) : 'localhost';
        $messageId = bin2hex(random_bytes(16)) . '@' . $domain;
        $headers->addIdHeader('Message-ID', $messageId);

        return $messageId;
    }
}
