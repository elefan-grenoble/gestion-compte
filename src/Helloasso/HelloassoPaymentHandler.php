<?php

declare(strict_types=1);

namespace App\Helloasso;

use App\Entity\HelloassoPayment;
use App\Event\HelloassoEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HelloassoPaymentHandler
{
    /** @var EntityManagerInterface */
    private $entityManager;

    private $helloassoPaymentRepository;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->helloassoPaymentRepository = $entityManager->getRepository(HelloassoPayment::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    public function savePayments(array $payments): array
    {
        $newPayments = [];

        foreach ($payments as $payment) {
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
            $this->logger->info(sprintf('Dispatch %s event for payement #%d', HelloassoEvent::PAYMENT_AFTER_SAVE, $payment->getId()));
            $this->eventDispatcher->dispatch(
                HelloassoEvent::PAYMENT_AFTER_SAVE,
                new HelloassoEvent($payment),
            );
        }

        return $newPayments;
    }
}
