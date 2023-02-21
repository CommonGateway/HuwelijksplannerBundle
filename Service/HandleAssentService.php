<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Action;
use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Exception;
use FontLib\Table\Type\os2;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

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
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var MessageBirdService
     */
    private MessageBirdService $messageBirdService;


    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $io;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var array
     */
    private array $configuration;

    /**
     * @param EntityManagerInterface $entityManager The Entity Manager
     * @param EventDispatcherInterface $eventDispatcher The Event Dispatcher
     * @param MessageBirdService $messageBirdService The MessageBird Service
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        MessageBirdService $messageBirdService
    ) {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->messageBirdService = $messageBirdService;
        $this->data = [];
        $this->configuration = [];
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
     * Get a schema by reference.
     *
     * @param string $reference The reference to look for
     *
     * @return Schema|null
     */
    public function getSchema(string $reference): ?Schema
    {
        $schema = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $reference]);
        if ($schema === null) {
            $this->logger->error("No schema found for $reference");
            isset($this->io) && $this->io->error("No schema found for $reference");
        }//end if

        return $schema;
    }//end getSchema()

    /**
     * Get an action by reference.
     *
     * @param string $reference The reference to look for
     *
     * @return Action|null
     */
    public function getAction(string $reference): ?Action
    {
        $action = $this->entityManager->getRepository('App:Action')->findOneBy(['reference' => $reference]);
        if ($action === null) {
            $this->logger->error("No action found for $reference");
            isset($this->io) && $this->io->error("No action found for $reference");
        }//end if

        return $action;
    }//end getAction()

    /**
     * Sends an emails
     *
     * @param object $emailAddresses
     * @param string $type
     * @return void
     */
    public function sendEmail(object $emailAddresses, string $type, array $data): void
    {
        // get action
        $action = $this->getAction('https://hp.nl/action/hp.HandleSendEmailAction.action.json');

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
        }

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
        }
    }//end sendEmail()

    /**
     * Sends a sms
     *
     * @param object $phoneNumbers
     * @param string $type
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
        }

        foreach ($phoneNumbers as $phoneNumber) {
            $this->messageBirdService->sendMessage($phoneNumber, $message);
        }
    }//end sendSms()

    /**
     * Handles the assent for the given person and sends an email or sms
     *
     * @param ObjectEntity|null $person
     * @param string $type
     * @return ObjectEntity|null
     */
    public function handleAssent(ObjectEntity $person, string $type, array $data): ?ObjectEntity
    {
        $assentSchema = $this->getSchema('https://huwelijksplanner.nl/schemas/hp.assent.schema.json');

        $assent = new ObjectEntity($assentSchema);
        $assent->hydrate([
            'name' => $person->getValue('voornaam'),
            'description' => null,
            'request' => null,
            'forwardUrl' => null,
            'property' => null,
            'process' => null,
            'contact' => $person,
            'status' => 'requested',
            'requester' => null, // the bsn of the person
            'revocable' => true
        ]);
        $this->entityManager->persist($assent);

        $phoneNumbers = $person->getValue('telefoonnummers');
        $emailAddresses = $person->getValue('emails');

        if ($emailAddresses === [] && $phoneNumbers === []) {
            throw new GatewayException('Email or phone number must be present', null, null, ['data' => 'telefoonnummers and/or emails', 'path' => 'Request body', 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        isset($this->io) && $this->io->info('hier mail of sms versturen en een secret genereren');

        $this->sendEmail($emailAddresses, $type, $data);
        $this->sendSms($phoneNumbers, $type);

        return $assent;
    }//end handleAssent()
}
