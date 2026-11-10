<?php

namespace App\Mail;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Tells whether the mails written by an admin should wait in the queue of the
 * background sending (sent by the crontab: command app:mail:send-pending) or
 * be sent during the request.
 *
 * MAIL_QUEUE_ENABLED=1 is not enough: a mail put in the queue of an
 * installation whose crontab does not run the command would wait for ever. So
 * the command leaves the time of its last run here, and the queue is only used
 * while that run is recent. Without crontab everything goes on as before.
 */
class MailQueueStatus
{
    /** The command runs every minute: five minutes without a run means that the crontab is not there. */
    public const MAX_AGE = 300;

    private const CACHE_KEY = 'app_mail_queue_last_run';

    /** @var CacheItemPoolInterface */
    private $cache;

    /** @var bool */
    private $enabled;

    public function __construct(CacheItemPoolInterface $cache, bool $enabled)
    {
        $this->cache = $cache;
        $this->enabled = $enabled;
    }

    /**
     * The command is running: remember it.
     */
    public function touch(): void
    {
        $item = $this->cache->getItem(self::CACHE_KEY);
        $item->set(time());
        $item->expiresAfter(86400 * 30);
        $this->cache->save($item);
    }

    public function lastRun(): ?\DateTimeImmutable
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        return $item->isHit() ? (new \DateTimeImmutable())->setTimestamp((int) $item->get()) : null;
    }

    /**
     * Whether the mails of an admin go to the queue: wanted (MAIL_QUEUE_ENABLED)
     * and the command ran recently.
     */
    public function shouldQueue(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $lastRun = $this->lastRun();

        return null !== $lastRun && time() - $lastRun->getTimestamp() <= self::MAX_AGE;
    }

    /**
     * Forgets the last run (tests).
     */
    public function reset(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY);
    }
}
