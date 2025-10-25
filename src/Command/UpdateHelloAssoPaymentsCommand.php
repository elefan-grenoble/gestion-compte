<?php

namespace App\Command;

use App\Entity\HelloassoPayment;
use App\Event\HelloassoEvent;
use App\Helper\Helloasso;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UpdateHelloAssoPaymentsCommand extends Command
{
    private $em;
    private $params;
    private $event_dispatcher;

    public function __construct(
        EntityManagerInterface $em,
        ContainerBagInterface $params,
        EventDispatcherInterface $event_dispatcher
    )
    {
        $this->em = $em;
        $this->params = $params;
        $this->event_dispatcher = $event_dispatcher;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:member:update_payments')
            ->setDescription('Update missing payments by browsing HelloAsso API')
            ->addOption('delay', '', InputOption::VALUE_REQUIRED, "Delay (example: '1 month')");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        # FIXME: this->getContainer ne fonctionne plus en symfony 4+
        $helloAssoClient = $this->getContainer()->get(Helloasso::class);

        // Campaign id needs to be 12 digits so let's add some 0 at the begining
        $campaignId = str_pad($this->params->get('helloasso_campaign_id'), 12, '0', STR_PAD_LEFT);
        $campaignJson = $helloAssoClient->get('campaigns/' . $campaignId);

        if (!$campaignJson){
            $output->writeln('Campaign not found :(');
            return 1;
        }

        $delay = $input->getOption('delay');
        // Compute the date to search from based on the delay
        $from = Carbon::now()->sub($delay);
        $output->writeln('Searching from ' . $from->toDateString());

        $paymentsJson = $helloAssoClient->get('campaigns/' . $campaignId . '/payments', array('from' => $from->toDateString()));

        if (!$paymentsJson || !isset($paymentsJson->resources) || !is_array($paymentsJson->resources) || !isset($paymentsJson->pagination)) {
            $output->writeln('Incorrect payments result :(');
            return 1;
        }

        $nbOfPages = $paymentsJson->pagination->max_page;
        $output->writeln('Found ' . count($paymentsJson->resources) . ' payments and ' . $nbOfPages . ' pages !');

        $this->processPage($paymentsJson->resources, $output);

        // Process additional pages if we have more
        for ($i = 2; $i <= $nbOfPages; $i++) {
            $output->writeln('Processing page ' . $i);
            $paymentsJson = $helloAssoClient->get('campaigns/' . $campaignId . '/payments', array('from' => $from->toDateString(), 'page' => $i));
            $this->processPage($paymentsJson->resources, $output);
        }

        return 0;
    }

    private function processPage(array $resources, OutputInterface $output)
    {
        foreach ($resources as $resource) {
            $this->processEntry($resource, $output);
        }
    }

    private function processEntry($entry, OutputInterface $output)
    {
        if (!isset($entry->payer_email) || !isset($entry->id) || !isset($entry->amount)) {
            $output->writeln('Unable to process an entry :(');
            return;
        }

        $email = $entry->payer_email;
        $paymentId = $entry->id;
        $amount = $entry->amount;
        $date = $entry->date;

        $output->write('Processing payment : ' . $date . ' / Email ' . $email . ' / Amount : ' . $amount . ' / payment id : ' . $paymentId . '  : ');

        $exist = $this->em->getRepository(HelloassoPayment::class)->findOneBy(array('paymentId' => $paymentId));

        if ($exist) {
            $output->writeln('Already exist, skipping...');
            return;
        }

        $payment = new HelloassoPayment();
        $payment->fromPaymentObj($entry, $this->params->get('helloasso_campaign_id'));

        $this->em->persist($payment);
        $this->em->flush();

        $this->event_dispatcher->dispatch(
            new HelloassoEvent($payment),
            HelloassoEvent::PAYMENT_AFTER_SAVE
        );

        $output->writeln(' DONE !');
    }
}
