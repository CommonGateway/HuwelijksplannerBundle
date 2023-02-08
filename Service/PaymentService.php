<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This service holds al the logic for mollie payments.
 */
class PaymentService
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $io
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
    }

    /**
     * Creates a payment object.
     *
     * @return array
     */
    public function createPayment(): array
    {
        isset($this->io) && $this->io->success('createPayment triggered');

        return [];
    }

    

    /**
     * Creates payment for given marriage.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @return array
     */
    public function createPaymentHandler(?array $data = [], ?array $configuration = []): array
    {
        isset($this->io) && $this->io->success('createPaymentHandler function triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        $payment = $this->createPayment();


        return $this->data;
    }
}
