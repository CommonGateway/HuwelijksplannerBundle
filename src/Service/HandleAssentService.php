<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Action;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * This service holds al the logic for approving or requesting a assent.
 */
class HandleAssentService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $gatewayResourceService;

    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var MessageBirdService
     */
    private MessageBirdService $messageBirdService;

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
     * @param EntityManagerInterface   $entityManager          The Entity Manager
     * @param GatewayResourceService   $gatewayResourceService The Gateway Resource Service
     * @param EventDispatcherInterface $eventDispatcher        The Event Dispatcher
     * @param MessageBirdService       $messageBirdService     The MessageBird Service
     * @param LoggerInterface          $pluginLogger           The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GatewayResourceService $gatewayResourceService,
        EventDispatcherInterface $eventDispatcher,
        MessageBirdService $messageBirdService,
        LoggerInterface $pluginLogger
    ) {
        $this->entityManager = $entityManager;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->eventDispatcher = $eventDispatcher;
        $this->messageBirdService = $messageBirdService;
        $this->pluginLogger = $pluginLogger;
        $this->data = [];
        $this->configuration = [];
    }//end __construct()

    /**
     * Sends an emails.
     *
     * @param object $emailAddresses The emailaddresses.
     * @param string $type           The type of the assent.
     * @param string $data           The data array of the request.
     *
     * @return void
     */
    public function sendEmail(object $emailAddresses, string $type, array $data): void
    {
        // Get action.
        $action = $this->gatewayResourceService->getAction('https://hp.nl/action/hp.HandleSendEmailAction.action.json', 'common-gateway/huwelijksplanner-bundle');

        $config = $action->getConfiguration();

        switch ($type) {
            case 'requester':
                $config['subject'] = 'Invite Assent request to requester';
                break;
            case 'partner':
                $config['subject'] = 'Invite Assent request to partner';
                break;
            case 'witness':
                $config['subject'] = 'Invite Assent request to witness';
                break;
            default:
                $config['subject'] = 'Invite Assent request';
                break;
        }//end switch

        // ? variables and data
        foreach ($emailAddresses as $emailAddress) {
            // set receiver to config
            $config['receiver'] = $emailAddress->getValue('email');
            $action->setConfiguration($config);

            $this->entityManager->persist($action);
            $this->entityManager->flush();

            // throw action event
            $event = new ActionEvent('commongateway.handler.pre', $data, 'hp.send.email');
            $this->eventDispatcher->dispatch($event, 'commongateway.handler.pre');
        }//end foreach
    }//end sendEmail()

    /**
     * Sends a sms.
     *
     * @param object $phoneNumbers The phonenumbers.
     * @param string $type         The type of the assent.
     *
     * @return void
     */
    public function sendSms(object $phoneNumbers, string $type): void
    {
        switch ($type) {
            case 'requester':
                $message = 'Assent request to requester';
                break;
            case 'partner':
                $message = 'Assent request to partner';
                break;
            case 'witness':
                $message = 'Assent request to witness';
                break;
            default:
                $message = 'Assent request';
                break;
        }//end switch

        foreach ($phoneNumbers as $phoneNumber) {
            $this->messageBirdService->sendMessage($phoneNumber, $message);
        }//end foreach
    }//end sendSms()

    /**
     * Handles the assent for the given person and sends an email or sms.
     *
     * @param ObjectEntity|null $person The person to make an assent for.
     * @param string            $type   The type of assent.
     * @param array             $data   The data of the request.
     *
     * @return ObjectEntity|null
     */
    public function handleAssent(ObjectEntity $person, string $type, array $data): ?ObjectEntity
    {
        // @TODO generate secret
        $assentSchema = $this->gatewayResourceService->getSchema('https://huwelijksplanner.nl/schemas/hp.assent.schema.json', 'common-gateway/huwelijksplanner-bundle');

        $assent = new ObjectEntity($assentSchema);
        $assent->hydrate([
            'name'        => $person->getValue('voornaam'),
            'description' => null,
            'request'     => null,
            'forwardUrl'  => null,
            'property'    => null,
            'process'     => null,
            'contact'     => $person,
            'status'      => 'requested',
            'requester'   => $type === 'requester' ? $person->getValue('subjectIdentificatie')->getValue('inpBsn') : null,
            'revocable'   => true,
        ]);
        $this->entityManager->persist($assent);
        $this->entityManager->flush();

        if (($phoneNumbers = $person->getValue('telefoonnummers')) === false) {
            $phoneNumbers = null;
        }//end if

        if (($emailAddresses = $person->getValue('emails')) === false) {
            $emailAddresses = null;
        }//end if

        if ($emailAddresses === null && $phoneNumbers === null) {
            return $assent;
        }//end if

        $this->pluginLogger->debug('hier mail of sms versturen en een secret genereren');

//        $this->sendEmail($emailAddresses, $type, $data); @TODO add mailgun before uncommenting
        $this->sendSms($phoneNumbers, $type);

        return $assent;
    }//end handleAssent()
}
