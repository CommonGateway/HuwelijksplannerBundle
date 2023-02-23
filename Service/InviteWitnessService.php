<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Psr\Log\LoggerInterface;

/**
 * This service holds al the logic for creating the marriage request object.
 */
class InviteWitnessService
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
     * @param EntityManagerInterface $entityManager          The Entity Manager
     * @param HandleAssentService    $handleAssentService    The Handle Assent Service
     * @param UpdateChecklistService $updateChecklistService The Update Checklist Service
     * @param Security               $security               The Security
     * @param LoggerInterface $logger The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        HandleAssentService $handleAssentService,
        UpdateChecklistService $updateChecklistService,
        Security $security,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->data = [];
        $this->configuration = [];
        $this->handleAssentService = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security = $security;
        $this->logger = $logger;
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
     * This function validates and creates the huwelijk object
     * and creates an assent for the current user.
     */
    private function inviteWitness(array $huwelijk, ?string $id): ?array
    {
        $huwelijkSchema = $this->getSchema('https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json');

        if (!$huwelijkObject = $this->entityManager->getRepository('App:ObjectEntity')->find($id)) {
            isset($this->io) && $this->io->error('Could not find huwelijk with id '.$id); // @TODO throw exception ?
            $this->logger->error('Could not find huwelijk with id '.$id);

            return null;

            throw new GatewayException('Could not find huwelijk with id '.$id);
        }

        if (isset($huwelijk['getuigen']) && count($huwelijk['getuigen']) <= 4) {
            // @TODO overwrite the witness array in the huwelijkObject
            // @TODO check if witness is aldready set
            $witnessAssents = [];
            foreach ($huwelijk['getuigen'] as $getuige) {
                $personSchema = $this->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json');
                $person = new ObjectEntity($personSchema);
                $person->hydrate($getuige['person']);
                $this->entityManager->persist($person);

                // creates an assent and add the person to the partners of this merriage
                $witnessAssents[] = $this->handleAssentService->handleAssent($person, 'witness', $this->data);
            }

            $huwelijkObject->setValue('getuigen', $witnessAssents);

            // @TODO update checklist with getuigen
            $huwelijkObject = $this->updateChecklistService->checkHuwelijk($huwelijkObject);

            $this->entityManager->persist($huwelijkObject);
            $this->entityManager->flush();
        }

        return $huwelijkObject->toArray();
    }//end createMarriage()

    /**
     * Creates the marriage request object.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @throws Exception
     *
     * @return ?array
     */
    public function inviteWitnessHandler(?array $data = [], ?array $configuration = []): ?array
    {
        isset($this->io) && $this->io->success('inviteWitnessHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        if (!isset($this->data['body'])) {
            isset($this->io) && $this->io->error('No data passed'); // @TODO throw exception ?
            $this->logger->error('No data passed');

            return ['response' => ['message' => 'No data passed'], 'httpCode' => 400];
        }

        if ($this->data['method'] !== 'PUT') {
            isset($this->io) && $this->io->error('Not a PUT request');
            $this->logger->error('Not a PUT request');

            return $this->data;
        }

        foreach ($this->data['path'] as $path) {
            if (Uuid::isValid($path)) {
                $id = $path;
            }
        }

        if (!isset($id)) {
            return $this->data;
        }

        $huwelijk = $this->inviteWitness($this->data['body'], $id);

        $this->data['response'] = new Response(
            json_encode($huwelijk),
            Response::HTTP_OK,
            ['content-type' => 'json']
        );

        return $this->data;
    }//end inviteWitnessHandler()
}
