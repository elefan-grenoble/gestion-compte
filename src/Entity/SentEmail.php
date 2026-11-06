<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A mail handed to the mail server by the application (history).
 *
 * The status only tells what the SMTP server answered: "sent" means that the
 * server accepted the message, not that it reached the mailbox.
 *
 * @ORM\Table(
 *     name="sent_email",
 *     indexes={
 *
 *         @ORM\Index(name="sent_email_created_at_idx", columns={"created_at"}),
 *         @ORM\Index(name="sent_email_status_idx", columns={"status"}),
 *         @ORM\Index(name="sent_email_message_id_idx", columns={"message_id"}),
 *         @ORM\Index(name="sent_email_batch_id_idx", columns={"batch_id"})
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\SentEmailRepository")
 */
class SentEmail
{
    /** Sent by the application itself, without anyone writing it (reminders, account mails...). */
    public const SOURCE_AUTO = 'auto';

    /** Written by an admin (the "send a mail" page or the mass mail command). */
    public const SOURCE_MANUAL = 'manual';

    /** Waiting for the background sending (command app:mail:send-pending). */
    public const STATUS_QUEUED = 'queued';

    /**
     * Being handed to the mail server (or interrupted before its answer). Shown as
     * "waiting" like the queued mails: the person does not need the difference.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const SOURCES = [
        'Automatique' => self::SOURCE_AUTO,
        'Manuel' => self::SOURCE_MANUAL,
    ];

    public const STATUSES = [
        'En attente' => self::STATUS_QUEUED,
        'Envoyé' => self::STATUS_SENT,
        'Échec' => self::STATUS_FAILED,
    ];

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(name="source", type="string", length=16)
     */
    private $source = self::SOURCE_AUTO;

    /**
     * @var string
     *
     * @ORM\Column(name="from_address", type="string", length=255)
     */
    private $fromAddress = '';

    /**
     * Every address the message goes to (to, cc and bcc), comma separated.
     *
     * @var string
     *
     * @ORM\Column(name="recipients", type="text")
     */
    private $recipients = '';

    /**
     * @var int
     *
     * @ORM\Column(name="recipient_count", type="integer")
     */
    private $recipientCount = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="subject", type="string", length=255)
     */
    private $subject = '';

    /**
     * Only kept for the mails written by a person (manual).
     *
     * @var null|string
     *
     * @ORM\Column(name="body", type="text", nullable=true)
     */
    private $body;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=16)
     */
    private $status = self::STATUS_PENDING;

    /**
     * @var null|string
     *
     * @ORM\Column(name="error", type="text", nullable=true)
     */
    private $error;

    /**
     * Message-ID header: the key to match a bounce to its mail later on.
     *
     * @var null|string
     *
     * @ORM\Column(name="message_id", type="string", length=255, nullable=true)
     */
    private $messageId;

    /**
     * Mails sent together (one per recipient, e.g. a personalised mail to a
     * group) share a batch: they are one line of the history.
     *
     * @var null|string
     *
     * @ORM\Column(name="batch_id", type="string", length=36, nullable=true)
     */
    private $batchId;

    /**
     * The mail to send (JSON, see App\Mail\QueuedMessage) while it waits in the
     * queue; emptied once it has been handed to the mail server.
     *
     * @var null|string
     *
     * @ORM\Column(name="queued_message", type="text", nullable=true)
     */
    private $queuedMessage;

    /**
     * @var null|User
     *
     * @ORM\ManyToOne(targetEntity="User")
     *
     * @ORM\JoinColumn(name="sent_by_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $sentBy;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(string $fromAddress): self
    {
        $this->fromAddress = mb_substr($fromAddress, 0, 255);

        return $this;
    }

    public function getRecipients(): string
    {
        return $this->recipients;
    }

    public function setRecipients(string $recipients): self
    {
        $this->recipients = $recipients;

        return $this;
    }

    public function getRecipientCount(): int
    {
        return $this->recipientCount;
    }

    public function setRecipientCount(int $recipientCount): self
    {
        $this->recipientCount = $recipientCount;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = mb_substr($subject, 0, 255);

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;

        return $this;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function setBatchId(?string $batchId): self
    {
        $this->batchId = $batchId;

        return $this;
    }

    public function getQueuedMessage(): ?string
    {
        return $this->queuedMessage;
    }

    public function setQueuedMessage(?string $queuedMessage): self
    {
        $this->queuedMessage = $queuedMessage;

        return $this;
    }

    public function getSentBy(): ?User
    {
        return $this->sentBy;
    }

    public function setSentBy(?User $sentBy): self
    {
        $this->sentBy = $sentBy;

        return $this;
    }
}
