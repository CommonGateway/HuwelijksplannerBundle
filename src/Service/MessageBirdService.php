<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

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
     * @var GatewayResourceService
     */
    private GatewayResourceService $gatewayResourceService;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $synchronizationService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;


    /**
     * @param EntityManagerInterface $entityManager          The Entity Manager
     * @param CallService            $callService            The Call Service
     * @param GatewayResourceService $gatewayResourceService The Gateway Resource Service
     * @param LoggerInterface        $pluginLogger           The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CallService $callService,
        GatewayResourceService $gatewayResourceService,
        SynchronizationService $synchronizationService,
        LoggerInterface $pluginLogger
    ) {
        $this->entityManager          = $entityManager;
        $this->callService            = $callService;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->synchronizationService = $synchronizationService;
        $this->pluginLogger           = $pluginLogger;

    }//end __construct()


    /**
     * @param $message
     *
     * @return ObjectEntity|null
     */
    public function importMessage($message): ?ObjectEntity
    {
        $messagebirdEntity = $this->gatewayResourceService->getSchema('https://huwelijksplanner.nl/schemas/hp.messagebird.schema.json', 'common-gateway/huwelijksplanner-bundle');
        $source            = $this->gatewayResourceService->getSource('https://rest.messagebird.com', 'common-gateway/huwelijksplanner-bundle');

        $synchronization = $this->synchronizationService->findSyncBySource($source, $messagebirdEntity, $message['id']);

        $this->pluginLogger->debug('Sending message from '.$message['originator']);

        $synchronization = $this->synchronizationService->synchronize($synchronization, $message);

        return $synchronization->getObject();

    }//end importMessage()


    /**
     * Handles sending a message with messagebird.
     *
     * @param string $recipients
     * @param string $body
     *
     * @return bool
     */
    public function sendMessage(string $recipients, string $body): bool
    {
        $this->pluginLogger->debug('Send a message');

        $messagebirdEntity = $this->gatewayResourceService->getSchema('https://huwelijksplanner.nl/schemas/hp.messagebird.schema.json', 'common-gateway/huwelijksplanner-bundle');
        $source            = $this->gatewayResourceService->getSource('https://rest.messagebird.com', 'common-gateway/huwelijksplanner-bundle');

        $config = ['body' => json_encode(['recipients' => $recipients, 'originator' => '+31612345678', 'body' => $body])];

        try {
            $response = $this->callService->call($source, '/messages', 'POST', $config);
        } catch (RequestException $exception) {
            $this->pluginLogger->error('Could not send the message with source: '.$source->getName());

            return false;
        }

        $message = json_decode($response->getBody()->getContents(), true);

        if (empty($message) === true) {
            $this->pluginLogger->error('Could not send the message with source: '.$source->getName());

            return false;
        }//end if

        $this->pluginLogger->debug('The message was sent successfully');

        $messageObject = new ObjectEntity($messagebirdEntity);
        $messageObject->hydrate($message);

        $this->entityManager->persist($messageObject);
        $this->entityManager->flush();

        return true;

    }//end sendMessage()


}//end class
