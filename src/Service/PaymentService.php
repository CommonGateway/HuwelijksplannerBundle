<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Gateway as Source;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This service holds al the logic for mollie payments.
 */
class PaymentService
{

    private EntityManagerInterface $entityManager;

    private SymfonyStyle $io;

    private ObjectRepository $sourceRepo;

    private ?Source $mollieAPI;

    private array $data;

    private array $configuration;


    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;

        $this->sourceRepo = $this->entityManager->getRepository(Source::class);

    }//end __construct()


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

    }//end setStyle()


    /**
     * Get the mollie api source.
     *
     * @return bool
     */
    private function getMollieSource(): bool
    {
        if (!$this->mollieAPI = $this->sourceRepo->findOneBy(['location' => 'https://api.mollie.com'])) {
            isset($this->io) && $this->io->error('No source found for https://api.mollie.com');

            throw new Exception('No source found for https://api.mollie.com');

            return false;
        }

        if (!$this->mollieAPI->getApiKey()) {
            isset($this->io) && $this->io->error('No api key set on mollie api source');

            throw new Exception('No api key set on mollie api source');

            return false;
        }

        return true;

    }//end getMollieSource()


    /**
     * Creates a payment object.
     *
     * @return array
     */
    public function createPayment(): array
    {
        isset($this->io) && $this->io->success('createPayment triggered');

        $huwelijkId = $this->data['parameters']->query->get('huwelijk') ?? null;
        // @TODO Validate hywelijk
        if (!$huwelijkId) {
            // @TODO throw exception
            return [];
        }

        $this->getMollieSource();

        // @TODO create mollie payment
        return [];

    }//end createPayment()


    /**
     * Creates payment for given marriage.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @return array
     */
    public function createPaymentHandler(?array $data=[], ?array $configuration=[]): array
    {
        isset($this->io) && $this->io->success('createPaymentHandler function triggered');
        $this->data          = $data;
        $this->configuration = $configuration;

        $payment = $this->createPayment();

        return ['response' => ['test' => 'test']];

    }//end createPaymentHandler()


}//end class