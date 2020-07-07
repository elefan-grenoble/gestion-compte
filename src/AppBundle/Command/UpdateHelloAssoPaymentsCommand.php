<?php

namespace AppBundle\Command;

use AppBundle\Entity\HelloassoPayment;
use AppBundle\Event\HelloassoEvent;
use AppBundle\Helper\Helloasso;
use Carbon\Carbon;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateHelloAssoPaymentsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:member:update_payments')
            ->setDescription('Update missing payments by browsing HelloAsso API')
            ->addOption('delay', '', InputOption::VALUE_REQUIRED, "Delay (example: '1 month')");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helloAssoClient = $this->getContainer()->get(Helloasso::class);

        // Campaign id needs to be 12 digits so let's add some 0 at the begining
        $campaignId = str_pad($this->getContainer()->getParameter('helloasso_campaign_id'), 12, '0', STR_PAD_LEFT);
        $campaignJson = $helloAssoClient->get('campaigns/' . $campaignId);

        if (!$campaignJson){
            $output->writeln('Campaign not found :(');
            return;
        }

        $delay = $input->getOption('delay');
        // Compute the date to search from based on the delay
        $from = Carbon::now()->sub($delay);
        $output->writeln('Searching from ' . $from->toDateString());

        $paymentsJson = $helloAssoClient->get('campaigns/' . $campaignId . '/payments', array('from' => $from->toDateString()));

        if (!$paymentsJson || !isset($paymentsJson->resources) || !is_array($paymentsJson->resources) || !isset($paymentsJson->pagination)) {
            $output->writeln('Incorrect payments result :(');
            return;
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

        $em = $this->getContainer()->get('doctrine')->getManager();
        $exist = $em->getRepository(HelloassoPayment::class)->findOneBy(array('paymentId' => $paymentId));

        if ($exist) {
            $output->writeln('Already exist, skipping...');
            return;
        }

        $payment = new HelloassoPayment();
        $payment->fromPaymentObj($entry, $this->getContainer()->getParameter('helloasso_campaign_id'));

        $em->persist($payment);
        $em->flush();

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(
            HelloassoEvent::PAYMENT_AFTER_SAVE,
            new HelloassoEvent($payment)
        );

        $output->writeln(' DONE !');
    }
}