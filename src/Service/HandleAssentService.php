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
     * Checks the config.
     *
     * @param string $config The config array from the action.
     *
     * @return array
     */
    public function checkConfig(array $config): array
    {
        if (key_exists('cc', $config) === true) {
            unset($config['cc']);
        }

        if (key_exists('bcc', $config) === true) {
            unset($config['bcc']);
        }

        if (key_exists('replyTo', $config) === true) {
            unset($config['replyTo']);
        }

        if (key_exists('priority', $config) === true) {
            unset($config['priority']);
        }

        return $config;

    }//end checkConfig()


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

        $configuration = $action->getConfiguration();
        $config        = $this->checkConfig($configuration);

        switch ($type) {
        case 'requester':
            $config['template'] = $config['template2'];
            break;
        case 'partner':
            $config['template'] = $config['template3'];
            break;
        case 'witness':
            $config['template'] = $config['template4'];
            break;
        }

        $config['serviceDNS'] = $source->getLocation().$source->getApiKey();

        // ? variables and data
        foreach ($emailAddresses as $emailAddress) {
            // set receiver to config
            $config['receiver'] = $emailAddress->getValue('email');
            $action->setConfiguration($config);

            $this->entityManager->persist($action);
            $this->entityManager->flush();

            // throw action event
            $event = new ActionEvent('commongateway.handler.pre', $data, 'huwelijksplanner.send.email');
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
        $action = $this->gatewayResourceService->getAction('https://hp.nl/action/hp.MessageBirdAction.action.json', 'common-gateway/huwelijksplanner-bundle');

        // Set the phoneNumbers to the recipients array.
        $data['response']['recipients'] = [];
        foreach ($phoneNumbers as $phoneNumber) {
            $data['response']['recipients'][] = $phoneNumber->getValue('telefoonnummer');
        }//end foreach

        // throw action event
        $event = new ActionEvent('commongateway.handler.pre', $data, 'huwelijksplanner.send.message');
        $this->eventDispatcher->dispatch($event, 'commongateway.handler.pre');

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
     * @param ObjectEntity|null $assent The assent of the person
     *
     * @return ObjectEntity|null
     */
    public function handleAssentEmailAndSms(ObjectEntity $person, string $type, array $data, ObjectEntity $assent): ObjectEntity
    {

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
            $this->sendEmail($emailAddresses, $type, $data);
            $this->sendSms($phoneNumbers, $type, $data);
        }

        return $assent;

    }//end handleAssentEmailAndSms()


    /**
     * Handles the assent for the given person and sends an email or sms.
     *
     * @param ObjectEntity      $person     The person to make/update an assent for.
     * @param string            $type       The type of assent.
     * @param array             $propertyId The id of the property this assent is about.
     * @param ObjectEntity|null $assent     The assent of the person
     *
     * @return ObjectEntity|null
     */
    public function handleAssent(ObjectEntity $person, string $type, string $propertyId, ?ObjectEntity $assent=null): ?ObjectEntity
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
                'huwelijk'    => $type === 'witness' ? $propertyId : null,
            ]
        );
        $this->entityManager->persist($assent);
        $this->entityManager->flush();

        return $assent;

    }//end handleAssent()


}//end class
