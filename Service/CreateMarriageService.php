<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Security;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use App\Exception\GatewayException;
use Symfony\Component\HttpFoundation\Response;


/**
 * This service holds al the logic for creating the marriage request object.
 */
class CreateMarriageService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $io;

    /**
     * @var HandleAssentService
     */
    private HandleAssentService $handleAssentService;

    /**
     * @var UpdateChecklistService
     */
    private UpdateChecklistService $updateChecklistService;

    /**
     * @var Security
     */
    private Security $security;

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
     * @param HandleAssentService $handleAssentService The Handle Assent Service
     * @param UpdateChecklistService $updateChecklistService The Update Checklist Service
     * @param Security $security The Security
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        HandleAssentService    $handleAssentService,
        UpdateChecklistService $updateChecklistService,
        Security $security
    )
    {
        $this->entityManager = $entityManager;
        $this->data = [];
        $this->configuration = [];
        $this->handleAssentService = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security = $security;
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
     * Get an schema by reference.
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
     * Validate huwelijk type.
     */
    private function validateType(array $huwelijk)
    {
        if (isset($huwelijk['type'])) {
            if (!$typeProductObject = $this->entityManager->getRepository('App:ObjectEntity')->find($huwelijk['type'])) {
                isset($this->io) && $this->io->error('huwelijk.type not found in the databse with given id');

                return ['response' => ['message' => 'huwelijk.type not found in the databse with given id'], 'httpCode' => 400];
            }

            if (!in_array($typeProductObject->getValue('upnLabel'), ['huwelijk', 'Omzetting', 'Partnerschap'])) {
                isset($this->io) && $this->io->error('huwelijk.type.upnLabel is not huwelijk, omzetten or partnerschap');

                return ['response' => ['message' => 'huwelijk.type.upnLabel is not huwelijk, Omzetting or Partnerschap'], 'httpCode' => 400];
            }

            return true;
        } else {
            isset($this->io) && $this->io->error('huwelijk.type is not given');

            return ['response' => ['message' => 'huwelijk.type is not given'], 'httpCode' => 400];
            throw new GatewayException('huwelijk.type is not given');
        }

        return true;
    }//end validateType()

    /**
     * Validate huwelijk type.
     *
     * @return array|bool $huwelijk OR false when invalid huwelijk
     */
    private function validateCeremonie(array $huwelijk)
    {
        if (isset($huwelijk['ceremonie'])) {
            if (!$ceremonieProductObject = $this->entityManager->getRepository('App:ObjectEntity')->find($huwelijk['ceremonie'])) {
                isset($this->io) && $this->io->error('huwelijk.ceremonie not found in the databse with given id');

                return ['response' => ['message' => 'huwelijk.ceremonie not found in the databse with given id'], 'httpCode' => 400];
            }

            if (!in_array($ceremonieProductObject->getValue('upnLabel'), ['gratis trouwen', 'flits/balliehuwelijk', 'eenvoudig huwelijk', 'uitgebreid huwelijk'])) {
                isset($this->io) && $this->io->error('huwelijk.ceremonie.upnLabel is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk');

                return ['response' => ['message' => 'huwelijk.ceremonie.upnLabel is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk'], 'httpCode' => 400];
            }

            return true;
        } else {
            isset($this->io) && $this->io->error('huwelijk.ceremonie is not given');

            return ['response' => ['message' => 'huwelijk.ceremonie is not given'], 'httpCode' => 400];
        }

        return true;
    }//end validateCeremonie()

    /**
     * This function creates a person object for the given user
     */
    private function createPerson(): ?ObjectEntity
    {
        $personSchema = $this->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json');

        // @TODO BRP person has to be set to the klantObject
        // @TODO get user/ person from jwt token and create a person object
        $person = new ObjectEntity($personSchema);
        $person->hydrate([
            'bronorganisatie' => null,
            'klantnummer' => null,
            'bedrijfsnaam' => null,
            'functie' => null,
            'websiteUrl' => null,
            'voornaam' => $this->security->getUser()->getFirstName(),
            'voorvoegselAchternaam' => null,
            'achternaam' => $this->security->getUser()->getLastName(),
            'telefoonnummers' => null,
            'emails' => [[
                'naam' => 'Emailadres van '. $this->security->getUser()->getFirstName(),
                'email' => $this->security->getUser()->getEmail()
            ]],
            'adressen' => null,
            'subject' => null,
            'subjectType' => 'natuurlijk_persoon',
            'subjectIdentificatie' => null,
        ]);
        $this->entityManager->persist($person);
        $this->entityManager->flush();

        return $person;
    }//end createPerson()

    /**
     * This function validates and creates the huwelijk object
     * and creates an assent for the current user.
     */
    private function createMarriage(array $huwelijk): ?array
    {
        $huwelijkSchema = $this->getSchema('https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json');

        $huwelijkObject = new ObjectEntity($huwelijkSchema);

        if ($this->validateType($huwelijk) && $this->validateCeremonie($huwelijk)) {

            // $huwelijk = $this->updateChecklistService->updateChecklist($huwelijk);

            if (!isset($huwelijk['message'])) {
                $huwelijkObject->hydrate($huwelijk);
                $this->entityManager->persist($huwelijkObject);

                $peron = $this->createPerson();
                // creates an assent and add the person to the partners of this merriage
                $requesterAssent['partners'][] = $this->handleAssentService->handleAssent($peron, 'requester', $this->data);
                $huwelijkObject->hydrate($requesterAssent);

                $this->entityManager->persist($huwelijkObject);
                $this->entityManager->flush();

                return $huwelijkObject->toArray();
            }
        }

        return ['response' => ['message' => 'Validation failed'], 'httpCode' => 400];

        // @TODO delete the huwelijk object if validation failed
    }//end createMarriage()

    /**
     * Creates the marriage request object.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @return ?array
     * @throws Exception
     *
     */
    public function createMarriageHandler(?array $data = [], ?array $configuration = []): ?array
    {
        isset($this->io) && $this->io->success('createMarriageHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;


        if (!isset($this->data['body'])) {
            isset($this->io) && $this->io->error('No data passed'); // @TODO throw exception ?

            return ['response' => ['message' => 'No data passed'], 'httpCode' => 400];
        }

        if ($this->data['method'] !== 'POST') {
            isset($this->io) && $this->io->error('Not a POST request');

            return ['response' => ['message' => 'Not a POST request'], 'httpCode' => 400];
        }

        $huwelijk = $this->createMarriage($this->data['body']);

        $this->data['response'] = new Response(
            json_encode($huwelijk),
            Response::HTTP_OK,
            ['content-type' => 'json']
        );

        return $this->data;
    }//end createMarriageHandler()
}
