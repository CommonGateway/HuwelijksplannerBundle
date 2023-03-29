<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Doctrine\Persistence\ObjectRepository;

/**
 * This service holds al the logic for mollie payments.
 */
class MollieWebhookService
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
    private SynchronizationService $syncService;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $gatewayResourceService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var array
     */
    private array $configuration;


    /**
     * @param EntityManagerInterface $entityManager          The Entity Manager Interface.
     * @param CallService            $callService            The Call Service.
     * @param SynchronizationService $syncService            The Synchronization Service.
     * @param GatewayResourceService $gatewayResourceService The Gateway Resource Service.
     * @param LoggerInterface        $pluginLogger           The Logger Interface.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CallService $callService,
        SynchronizationService $syncService,
        GatewayResourceService $gatewayResourceService,
        LoggerInterface $pluginLogger
    ) {
        $this->entityManager          = $entityManager;
        $this->callService            = $callService;
        $this->syncService            = $syncService;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->pluginLogger           = $pluginLogger;

        $this->data          = [];
        $this->configuration = [];

    }//end __construct()


    /**
     * Check the auth of the given source.
     *
     * @param Source $source The given source to check the api key.
     *
     * @return bool If the api key is set or not.
     */
    public function checkSourceAuth(Source $source): bool
    {
        if ($source->getApiKey() === null) {
            $this->pluginLogger->error('No auth set for Source: '.$source->getName().'.', ['plugin' => 'common-gateway/huwelijksplanner-bundle']);

            return false;
        }//end if

        return true;

    }//end checkSourceAuth()


    /**
     * Creates payment for given marriage.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @return array
     */
    public function mollieWebhookHandler(?array $data=[], ?array $configuration=[]): array
    {
        $this->pluginLogger->debug('mollieWebhookHandler triggered');
        $this->data          = $data;
        $this->configuration = $configuration;

        if (empty($this->data['parameters']['body']['id']) === true) {
            return $this->data;
        }

        $id = $this->data['parameters']['body']['id'];

        $mollieEntity = $this->gatewayResourceService->getSchema('https://huwelijksplanner.nl/schemas/hp.mollie.schema.json', 'common-gateway/huwelijksplanner-bundle');
        $source       = $this->gatewayResourceService->getSource('https://huwelijksplanner.nl/source/hp.mollie.source.json', 'common-gateway/huwelijksplanner-bundle');
        if ($this->checkSourceAuth($source) === false) {
            return [
                'message' => 'No authorization set for the mollie source.',
                'status'  => 400,
            ];
        }//end if

        try {
            $response = $this->callService->call($source, '/v2/payments/'.$id, 'GET');
            $payment  = json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $exception) {
            $this->pluginLogger->error('Could not get a payment with source: '.$source->getName().' and id: '.$id);
        }

        if (empty($payment) === true) {
            $this->pluginLogger->error('Could not get a payment with source: '.$source->getName().' and id: '.$id);

            return [
                'response' => [
                    'message' => 'Could not get a payment with source: '.$source->getName().' and id: '.$id,
                    'status'  => 400,
                ]
            ];
        }//end if

        // If we dont have a checkout url from mollie return a 502.
        if (isset($payment['_links']['checkout']) === false) {
            return [
                'response' => [
                    'message' => 'Payment object created from mollie but no checkout url provided',
                    'status'  => 502,
                ]
            ];
        }//end if

        $synchronization = $this->syncService->findSyncBySource($source, $mollieEntity, $this->data['parameters']['body']['id']);
        $this->pluginLogger->debug('Sync with id: '.$synchronization->getId()->toString());

        $synchronization = $this->syncService->synchronize($synchronization, $payment);

        return ['response' => ['checkout' => $payment['_links']['checkout']]];

    }//end mollieWebhookHandler()


}//end class
