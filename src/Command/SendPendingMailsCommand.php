<?php

namespace App\Command;

use App\Mail\MailQueueSender;
use App\Mail\MailQueueStatus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sends the mails waiting in the history (see MAIL_QUEUE_ENABLED): to be run
 * every minute by the crontab.
 */
class SendPendingMailsCommand extends Command
{
    /** @var MailQueueSender */
    private $sender;

    /** @var MailQueueStatus */
    private $status;

    public function __construct(MailQueueSender $sender, MailQueueStatus $status)
    {
        $this->sender = $sender;
        $this->status = $status;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:mail:send-pending')
            ->setDescription('Send the mails waiting in the queue (background sending)')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of mails per run', 500)
            ->addOption('max-seconds', null, InputOption::VALUE_REQUIRED, 'Stop taking new mails after this time in seconds (0: no limit)', 50)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // The page "send a mail" only uses the queue while this command runs (see MailQueueStatus).
        $this->status->touch();

        $result = $this->sender->send((int) $input->getOption('limit'), (int) $input->getOption('max-seconds'));

        if (null === $result) {
            $output->writeln('Another run is already sending the mails.');

            return 0;
        }

        $output->writeln(sprintf('%d sent, %d failed, %d still waiting.', $result['sent'], $result['failed'], $result['remaining']));

        return 0;
    }
}
