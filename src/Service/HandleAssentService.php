<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Action;
use App\Entity\Gateway as Source;
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
        $this->entityManager          = $entityManager;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->eventDispatcher        = $eventDispatcher;
        $this->messageBirdService     = $messageBirdService;
        $this->pluginLogger           = $pluginLogger;
        $this->data                   = [];
        $this->configuration          = [];

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
        $source = $this->gatewayResourceService->getSource('https://huwelijksplanner.nl/source/hp.sendInBlue.source.json', 'common-gateway/huwelijksplanner-bundle');
        if ($this->checkSourceAuth($source) === false) {
            // logger
        }//end if

        $config = $action->getConfiguration();

        switch ($type) {
        case 'requester':
            $config['subject'] = 'Melding Voorgenomen Huwelijk';

            $config['template'] = $config['templateRequester'];
            break;
        case 'partner':
            $config['subject']  = 'Melding Voorgenomen Huwelijk';
            $config['template'] = $config['templatePartner'];
            break;
        case 'witness':
            $config['subject']  = 'Melding Voorgenomen Huwelijk';
            $config['template'] = $config['templateWitness'];
            break;
        default:
            // @TODO throw error
            break;
        }//end switch

        $config['serviceDNS'] = $source->getLocation().$source->getApiKey();

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
    public function sendSms(object $phoneNumbers, string $type, array $data): void
    {
        switch ($type) {
        case 'requester':
            $message = 'Melding Voorgenomen Huwelijk';
            break;
        case 'partner':
            $message = 'Beste '.$data['response']['partnerNaam'].', '.$data['response']['assentNaam'].' '.$data['response']['assentDescription'].' '.$data['response']['url'];
            break;
        case 'witness':
            $message = 'Beste '.$data['response']['partnerNaam'].', '.$data['response']['assentNaam'].' '.$data['response']['assentDescription'].' '.$data['response']['url'];
            break;
        default:
            $message = 'Assent request';
            break;
        }//end switch

        foreach ($phoneNumbers as $phoneNumber) {
            $this->messageBirdService->sendMessage($phoneNumber->getValue('telefoonnummer'), $message);
        }//end foreach

    }//end sendSms()


    /**
     * Determines the status of the assent based on if the assent contains the bsn of the assentee.
     *
     * @param string       $type   The type of assent.
     * @param ObjectEntity $person The assentee of the assent.
     *
     * @return string
     */
    public function getStatus(string $type, ObjectEntity $person): string
    {
        if ($type === 'requester'
            || ($type === 'partner'
            && $person->getValue('subjectIdentificatie') !== false
            && $person->getValue('subjectIdentificatie')->getValue('inpBsn') !== false)
        ) {
            return 'granted';
        }

        return 'requested';

    }//end getStatus()


    /**
     * Handles the assent for the given person and sends an email or sms.
     *
     * @param ObjectEntity      $person The person to make/update an assent for.
     * @param string            $type   The type of assent.
     * @param array             $data   The data of the request.
     * @param array             $data   The id of the property this assent is about.
     * @param ObjectEntity|null $assent The assent of the person
     *
     * @return ObjectEntity|null
     */
    public function handleAssent(ObjectEntity $person, string $type, array $data, string $propertyId, ?ObjectEntity $assent = null): ?ObjectEntity
    {
        // @TODO generate secret
        $assentSchema = $this->gatewayResourceService->getSchema('https://huwelijksplanner.nl/schemas/hp.assent.schema.json', 'common-gateway/huwelijksplanner-bundle');

        if ($assent === null) {
            $assent = new ObjectEntity($assentSchema);
        }

        $assent->hydrate(
            [
                'name'        => $person->getValue('voornaam'),
                'description' => null,
                'request'     => null,
                'forwardUrl'  => null,
                'property'    => $propertyId,
                'process'     => null,
                'contact'     => $person,
                'status'      => $this->getStatus($type, $person),
                'requester'   => $type === 'requester' ? $person->getValue('subjectIdentificatie')->getValue('inpBsn') : null,
                'revocable'   => true,
            ]
        );
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

        if ($assent->getValue('status') !== 'granted') {
            $data['response']['url'] = $data['response']['url'].$assent->getId()->toString();

            $this->sendEmail($emailAddresses, $type, $data);
            $this->sendSms($phoneNumbers, $type, $data);
        }

        return $assent;

    }//end handleAssent()


}//end class
