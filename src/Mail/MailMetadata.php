<?php

namespace App\Mail;

use App\Entity\EmailLog;
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

    public const HEADERS = [
        self::HEADER_SOURCE,
        self::HEADER_STORE_BODY,
        self::HEADER_SENT_BY,
        self::HEADER_BATCH,
    ];

    /**
     * Mail written by a person: its body is kept in the history.
     *
     * The mails given the same $batchId (one mail per recipient of a group)
     * are shown as one line of the history. They usually share the same text:
     * to keep it only once, give $keepBody to the first mail of the group only
     * (the history then shows it for the whole group).
     */
    public static function written(Email $email, ?User $sentBy = null, ?string $batchId = null, bool $keepBody = true): Email
    {
        $headers = $email->getHeaders();
        $headers->addTextHeader(self::HEADER_SOURCE, EmailLog::SOURCE_MANUAL);
        if ($keepBody) {
            $headers->addTextHeader(self::HEADER_STORE_BODY, '1');
        }
        if ($batchId) {
            $headers->addTextHeader(self::HEADER_BATCH, $batchId);
        }
        if ($sentBy && $sentBy->getId()) {
            $headers->addTextHeader(self::HEADER_SENT_BY, (string) $sentBy->getId());
        }

        return $email;
    }
}
