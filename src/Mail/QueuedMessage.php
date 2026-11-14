<?php

namespace App\Mail;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * A mail kept in the history until the background sending hands it to the mail
 * server (see MailQueueSender).
 *
 * The mail is stored as JSON, field by field, rather than as a serialized
 * object. Only plain mails (addresses, subject, text and/or HTML) can wait in
 * the queue: a mail with attachments is sent at once.
 */
final class QueuedMessage
{
    private const ADDRESS_FIELDS = ['from' => 'getFrom', 'to' => 'getTo', 'cc' => 'getCc', 'bcc' => 'getBcc', 'replyTo' => 'getReplyTo'];

    /**
     * @return null|string the JSON of the mail, null when it cannot wait in the queue
     */
    public static function encode(Email $email): ?string
    {
        if ($email->getAttachments()) {
            return null;
        }

        $data = [];
        foreach (self::ADDRESS_FIELDS as $field => $getter) {
            $data[$field] = array_map(static function (Address $address) {
                return [$address->getAddress(), $address->getName()];
            }, $email->{$getter}());
        }
        $data['subject'] = (string) $email->getSubject();
        $data['html'] = self::read($email->getHtmlBody());
        $data['text'] = self::read($email->getTextBody());

        $headers = $email->getHeaders();
        $data['messageId'] = $headers->has('Message-ID') ? trim($headers->get('Message-ID')->getBodyAsString(), '<>') : null;

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return false === $json ? null : $json;
    }

    public static function decode(string $json): Email
    {
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['from']) || (empty($data['to']) && empty($data['cc']) && empty($data['bcc']))) {
            throw new \InvalidArgumentException('The queued mail is unreadable.');
        }

        $email = new Email();
        $setters = ['from' => 'from', 'to' => 'to', 'cc' => 'cc', 'bcc' => 'bcc', 'replyTo' => 'replyTo'];
        foreach ($setters as $field => $setter) {
            $addresses = [];
            foreach ($data[$field] ?? [] as $pair) {
                $addresses[] = new Address($pair[0], $pair[1] ?? '');
            }
            if ($addresses) {
                $email->{$setter}(...$addresses);
            }
        }
        $email->subject($data['subject'] ?? '');
        if (isset($data['html'])) {
            $email->html($data['html']);
        }
        if (isset($data['text'])) {
            $email->text($data['text']);
        }
        if (!empty($data['messageId'])) {
            $email->getHeaders()->addIdHeader('Message-ID', $data['messageId']);
        }

        return $email;
    }

    /**
     * @param null|resource|string $body
     */
    private static function read($body): ?string
    {
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return null === $body ? null : (string) $body;
    }
}
