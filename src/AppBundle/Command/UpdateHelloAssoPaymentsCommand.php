<?php

namespace AppBundle\Command;

use AppBundle\Entity\HelloassoPayment;
use AppBundle\Event\HelloassoEvent;
use AppBundle\Helloasso\HelloassoClient;
use AppBundle\Repository\HelloassoPaymentRepository;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UpdateHelloAssoPaymentsCommand extends ContainerAwareCommand
{
    /** @var HelloassoClient */
    private $helloassoClient;

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var HelloassoPaymentRepository */
    private $helloassoPaymentRepository;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        HelloassoClient $helloassoClient,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        parent::__construct('app:member:update_payments');
        $this->helloassoClient = $helloassoClient;
        $this->entityManager = $entityManager;
        $this->helloassoPaymentRepository = $entityManager->getRepository(HelloassoPayment::class);
        $this->eventDispatcher = $eventDispatcher;
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

        $newPayments = [];

        foreach ($results->data as $payment) {
            $existingPayement = $this->helloassoPaymentRepository->findOneBy(['paymentId' => $payment->id]);
            if ($existingPayement instanceof HelloassoPayment) {
                $this->logger->info(sprintf('Payment #%d is already in database', $payment->id));
                continue;
            }

            $this->logger->info(sprintf('Processing payment #%d for user %s', $payment->id, $payment->payer->email));
            $payementEntity = HelloassoPayment::createFromPayementObject($payment);
            $this->entityManager->persist($payementEntity);
            $newPayments[] = $payementEntity;
        }

        $this->entityManager->flush();

        foreach ($newPayments as $payment) {
            $this->eventDispatcher->dispatch(
                HelloassoEvent::PAYMENT_AFTER_SAVE,
                new HelloassoEvent($payment),
            );
        }

        if ($page < $lastPage) {
            $this->processPage($formSlug, $from, $page + 1);
        }
    }
}