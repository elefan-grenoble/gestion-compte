<?php

namespace App\Mail;

use App\Entity\SentEmail;
use App\Entity\User;
use Symfony\Component\Mime\Email;

/**
 * Tells the mail history (see RecordingMailer) where a mail comes from.
 *
 * The information travels in internal headers of the message: the
 * RecordingMailer reads them and removes them before the mail leaves, so the
 * recipients never see them.
 */
final class MailMetadata
{
    public const HEADER_SOURCE = 'X-App-Mail-Source';
    public const HEADER_STORE_BODY = 'X-App-Store-Body';
    public const HEADER_SENT_BY = 'X-App-Sent-By';
    public const HEADER_BATCH = 'X-App-Mail-Batch';
    public const HEADER_QUEUE = 'X-App-Mail-Queue';

    public const HEADERS = [
        self::HEADER_SOURCE,
        self::HEADER_STORE_BODY,
        self::HEADER_SENT_BY,
        self::HEADER_BATCH,
        self::HEADER_QUEUE,
    ];

    /**
     * Mail written by a person: its body is kept in the history.
     *
     * The mails given the same $batchId (one mail per recipient of a group)
     * are shown as one line of the history.
     */
    public static function written(Email $email, ?User $sentBy = null, ?string $batchId = null): Email
    {
        $headers = $email->getHeaders();
        $headers->addTextHeader(self::HEADER_SOURCE, SentEmail::SOURCE_MANUAL);
        $headers->addTextHeader(self::HEADER_STORE_BODY, '1');
        if ($batchId) {
            $headers->addTextHeader(self::HEADER_BATCH, $batchId);
        }
        if ($sentBy && $sentBy->getId()) {
            $headers->addTextHeader(self::HEADER_SENT_BY, (string) $sentBy->getId());
        }

        return $email;
    }

    /**
     * Asks to send the mail in the background: it waits in the history with
     * the status "queued" until the command app:mail:send-pending sends it.
     * A mail that cannot wait (attachments) is sent at once.
     */
    public static function queue(Email $email): Email
    {
        $email->getHeaders()->addTextHeader(self::HEADER_QUEUE, '1');

        return $email;
    }
}
