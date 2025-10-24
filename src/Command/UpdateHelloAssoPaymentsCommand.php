<?php

namespace App\Command;

use App\Helloasso\HelloassoClient;
use App\Helloasso\HelloassoPaymentHandler;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateHelloAssoPaymentsCommand extends Command
{
    /** @var HelloassoClient */
    private $helloassoClient;

    /** @var HelloassoPaymentHandler */
    private $paymentHandler;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        HelloassoClient $helloassoClient,
        HelloassoPaymentHandler $paymentHandler,
        LoggerInterface $logger
    ) {
        parent::__construct('app:member:update_payments');
        $this->helloassoClient = $helloassoClient;
        $this->paymentHandler = $paymentHandler;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setDescription('Update missing payments by browsing HelloAsso API')
            ->addOption('delay', '', InputOption::VALUE_REQUIRED, "Delay (example: '1 month')");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formSlug = $this->getContainer()->getParameter('helloasso_campaign_slug');
        $from = Carbon::now()->sub($input->getOption('delay'));

        $output->writeln('Searching from '.$from->format('Y-m-d'));

        $this->processPage($formSlug, $from, 1);

        return 0;
    }

    private function processPage(string $formSlug, \DateTimeInterface $from, int $page)
    {
        $this->logger->info('Fetching page '.$page);
        $results = $this->helloassoClient->getFormPayments(
            'Membership',
            $formSlug,
            ['from' => $from->format('Y-m-d'), 'page' => $page],
        );
        $lastPage = max($results->pagination->totalPages, 1);
        $this->logger->info(sprintf('%d results in page %d', count($results->data), $page));

        $this->paymentHandler->savePayments($results->data);

        if ($page < $lastPage) {
            $this->processPage($formSlug, $from, $page + 1);
        }
    }
}
