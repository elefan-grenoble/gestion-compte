<?php

namespace App\Tests\Unit\Mail;

use App\Mail\MailQueueStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
class MailQueueStatusTest extends TestCase
{
    public function testTheQueueIsOnlyUsedWhileTheCommandRuns(): void
    {
        $status = new MailQueueStatus(new ArrayAdapter(), true);
        $this->assertFalse($status->shouldQueue(), 'The command never ran: nobody would send the queue.');

        $status->touch();
        $this->assertTrue($status->shouldQueue());

        $status->reset();
        $this->assertFalse($status->shouldQueue());
    }

    public function testTheQueueIsNotUsedWhenItIsSwitchedOffOrTheCommandStoppedRunning(): void
    {
        $off = new MailQueueStatus(new ArrayAdapter(), false);
        $off->touch();
        $this->assertFalse($off->shouldQueue());

        $cache = new ArrayAdapter();
        $item = $cache->getItem('app_mail_queue_last_run');
        $item->set(time() - MailQueueStatus::MAX_AGE - 1);
        $cache->save($item);
        $this->assertFalse((new MailQueueStatus($cache, true))->shouldQueue());
    }
}
