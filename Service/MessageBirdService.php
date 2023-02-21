<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use CommonGateway\CoreBundle\Service\CallService;
use App\Service\SynchronizationService;
use Doctrine\ORM\PersistentCollection;
use DoctrineExtensions\Query\Mysql\Soundex;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Response;

/**
 * This service holds all the logic for sending a message with messagebird.
 */
class MessageBirdService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CallService
     */
    private CallService $callService;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $synchronizationService;

    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $io;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param EntityManagerInterface $entityManager The Entity Manager
     * @param CallService $callService The Call Service
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CallService $callService,
        SynchronizationService $synchronizationService
    ) {
        $this->entityManager = $entityManager;
        $this->callService = $callService;
        $this->synchronizationService = $synchronizationService;
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
    }//end setStyle()

    /**
     * Get an schema by reference.
     *
     * @param string $reference The reference to look for
     *
     * @return Schema|null
     */
    public function getEntity(string $reference): ?Schema
    {
        $schema = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $reference]);
        if ($schema === null) {
            $this->logger->error("No schema found for $reference");
            isset($this->io) && $this->io->error("No schema found for $reference");
        }//end if

        return $schema;
    }//end getSchema()

    /**
     * Gets source for location.
     *
     * @param string $location The location to look for
     *
     * @return Source
     */
    public function getSource(string $location): Source
    {
        $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['location' => $location]);
        if ($source === null) {
            $this->logger->error("No source found for $location");
        }

        return $source;
    }//end getSource()

    /**
     * @param $message
     *
     * @return ObjectEntity|null
     */
    public function importMessage($message): ?ObjectEntity
    {
        $messagebirdEntity = $this->getEntity('https://huwelijksplanner.nl/schemas/hp.messagebird.schema.json');
        $source = $this->getSource('https://rest.messagebird.com');

        $synchronization = $this->synchronizationService->findSyncBySource($source, $messagebirdEntity, $message['id']);

        $this->logger->comment('Sending message from '.$message['originator']);

        $synchronization = $this->synchronizationService->synchronize($synchronization, $message);

        return $synchronization->getObject();
    }//end importMessage()

    /**
     * Handles sending a message with messagebird
     *
     * @param string $recipients
     * @param string $body
     * @return bool
     */
    public function sendMessage(string $recipients, string $body): bool
    {
            isset($this->logger) ?? $this->logger->info('Send a message');

        $messagebirdEntity = $this->getEntity('https://huwelijksplanner.nl/schemas/hp.messagebird.schema.json');
        $source = $this->getSource('https://rest.messagebird.com');

        $config = [
            'body' => json_encode([
                'recipients' => $recipients,
                'originator' => '+31612345678',
                'body' => $body
            ])
        ];

        $response = $this->callService->call($source, '/messages', 'POST', $config);

        $message = json_decode($response->getBody()->getContents(), true);

        if (!$message) {
            $this->logger->error('Could not send the message with source: '.$source->getName());

            return false;
        }

        $this->logger->success('The message was sent successfully');

        $messageObject = new ObjectEntity($messagebirdEntity);
        $messageObject->hydrate($message);

        $this->entityManager->persist($messageObject);
        $this->entityManager->flush();

        return true;
    }//end sendMessage()
}