<?php
// src/App/Command/ShiftGenerateCommand.php
namespace App\Command;

use App\Entity\HelloassoPayment;
use App\Event\HelloassoEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HelloassoPaymentCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function configure()
    {
        $this
            ->setName('app:helloasso:payment')
            ->setDescription('Tools for helloasso payment')
            ->setHelp('This command is an helper for helloasso payment')
            ->addArgument('save_after_id', InputArgument::OPTIONAL, 'a payment id to trigger a "save after" on')
            ->addOption('list_orphan','lo',InputOption::VALUE_NONE,'List orphan payments')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $save_after_id = $input->getArgument('save_after_id');
        $list_orphan = $input->getOption('list_orphan');

        if ($list_orphan){
            $orphans = $this->entityManager->getRepository(HelloassoPayment::class)->findOrphan();
            if (count($orphans)){
                $output->writeln("<info>Listing orphan payments below (".count($orphans).")</info>");
                /** @var HelloassoPayment $orphan */
                foreach ($orphans as $orphan){
                    $output->writeln(
                        '#'.$orphan->getId().' - '.
                        $orphan->getEmail().' - '.
                        strtolower($orphan->getPayerFirstName()).' '.
                        strtoupper($orphan->getPayerLastName()).' - '.
                        $orphan->getDate()->format('d/m/y H:i'));
                }
            }else{
                $output->writeln("<info>No orphan found !</info>");
            }
        }

        if ($save_after_id){
            $payment = $this->entityManager->getRepository(HelloassoPayment::class)->findOneBy(array('id'=>$save_after_id));
            if ($payment){
                $output->writeln("<info>Event HelloassoEvent::PAYMENT_AFTER_SAVE (".HelloassoEvent::PAYMENT_AFTER_SAVE.") will be triggered on payment id #".$payment->getId()."</info>");
                $this->eventDispatcher->dispatch(
                    HelloassoEvent::PAYMENT_AFTER_SAVE,
                    new HelloassoEvent($payment)
                );
            }else{
                $output->writeln("<error>No payement found for id #".$save_after_id."</error>");
            }
        }
    }

}