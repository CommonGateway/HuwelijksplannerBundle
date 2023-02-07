<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Exception;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\Entity as Schema;
use Doctrine\Persistence\ObjectRepository;
use CommonGateway\HuwelijksplannerBundle\Service\HandleAssentService;
use CommonGateway\HuwelijksplannerBundle\Service\UpdateChecklistService;

/**
 * This service holds al the logic for creating the marriage request object.
 */
class CreateMarriageService
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;
    private array $data;
    private array $configuration;
    private HandleAssentService $handleAssentService;
    private UpdateChecklistService $updateChecklistService;

    private ObjectRepository $schemaRepo;
    private ObjectRepository $objectRepo;

    private ?Schema $huwelijkSchema;

    /**
     * @param ObjectEntityService    $objectEntityService
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        HandleAssentService $handleAssentService,
        UpdateChecklistService $updateChecklistService
    ) {
        $this->entityManager = $entityManager;
        $this->data = [];
        $this->configuration = [];
        $this->handleAssentService = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;

        $this->schemaRepo = $this->entityManager->getRepository(Schema::class);
        $this->objectRepo = $this->entityManager->getRepository(ObjectEntity::class);
        $this->huwelijkSchema = $this->schemaRepo->findOneBy(['reference' => 'https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json']);
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
     * Get the huwelijk schema.
     *
     * @return bool
     */
    private function getHuwelijkSchema(): bool
    {
        if (!$this->huwelijkSchema = $this->schemaRepo->findOneBy(['reference' => 'https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json'])) {
            isset($this->io) && $this->io->error('No schema found for https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json');
            throw new Exception('No schema found for https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json');

            return false;
        }

        return true;
    }

    /**
     * Validate huwelijk type.
     */
    private function validateType(array $huwelijk)
    {
        if (isset($huwelijk['type'])) {
            if (!$typeProductObject = $this->objectRepo->find($huwelijk['type'])) {
                isset($this->io) && $this->io->error('huwelijk.type not found in the databse with given id'); 
                throw new Exception('huwelijk.type not found in the databse with given id');
            }

            if (!in_array($typeProductObject->getValue('upnLabel'), ['huwelijk', 'Omzetting', 'Partnerschap'])) {
                isset($this->io) && $this->io->error('huwelijk.type is not huwelijk, omzetten or partnerschap');

                throw new Exception('huwelijk.type is not huwelijk, Omzetting or Partnerschap');
            }
        }
    }

    /**
     * Validate huwelijk type.
     *
     * @return array|bool $huwelijk OR false when invalid huwelijk
     */
    private function validateCeremonie(array $huwelijk)
    {
        if (isset($huwelijk['ceremonie'])) {
            if (!$ceremonieProductObject = $this->objectRepo->find($huwelijk['ceremonie'])) {
                isset($this->io) && $this->io->error('huwelijk.type not found in the databse with given id'); 

                throw new Exception('huwelijk.type not found in the databse with given id');
            }

            if (!in_array($ceremonieProductObject->getValue('upnLabel'), ['gratis trouwen', 'flits/balliehuwelijk', 'eenvoudig huwelijk', 'uitgebreid huwelijk'])) {
                isset($this->io) && $this->io->error('huwelijk.type is not huwelijk, omzetten or partnerschap'); 

                throw new Exception('huwelijk.ceremonie is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk');
            }
        }
    }

    /**
     * Validate the huwelijk object.
     */
    private function validateMarriage(array $huwelijk)
    {
        $this->validateType($huwelijk);
        $this->validateCeremonie($huwelijk);
    }

    private function createMarriage(array $huwelijk, ?string $id)
    {
        // test
        // var_dump($this->io->info($security->getUser()->getUserIdentifier()));

        $this->getHuwelijkSchema();

        if (isset($this->data['response']['id'])) {
            if (!$huwelijkObject = $this->objectRepo->find($this->data['response']['id'])) {
                isset($this->io) && $this->io->error('Could not find huwelijk with id ' . $this->data['response']['id']); // @TODO throw exception ?
                throw new Exception('Could not find huwelijk with id ' . $this->data['response']['id']);
            }
        } else {
            $huwelijkObject = new ObjectEntity($this->huwelijkSchema);
        }

        try {
            $this->validateMarriage($huwelijk);
        } catch (Exception $e) {
            $this->entityManager->remove($huwelijkObject); // delete if error
            $this->entityManager->flush();
            throw new Exception($e->getMessage());
        }

        // $huwelijk = $this->handleAssentService->handleAssent($huwelijk);
        // $huwelijk = $this->updateChecklistService->updateChecklist($huwelijk);

        // If message aint set 
        if (!isset($huwelijk['message'])) {
            $huwelijkObject->hydrate($huwelijk);
            $this->entityManager->persist($huwelijkObject);
            $huwelijk = $huwelijkObject->toArray();
        } else {
            $this->entityManager->remove($huwelijkObject); // delete if error
        }
        $this->entityManager->flush();

        return $huwelijk;
    }

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
    public function createMarriageHandler(?array $data = [], ?array $configuration = []): ?array
    {
        isset($this->io) && $this->io->success('createMarriageHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        if (!isset($this->data['request'])) {
            isset($this->io) && $this->io->error('No data passed'); // @TODO throw exception ?
            return ['response' => ['message' => 'No data passed'], 'httpCode' => 400];
        }

        if (!$method = $this->data['parameters']->getMethod()) {
            isset($this->io) && $this->io->error('Method not set'); // @TODO throw exception ?
            return ['response' => ['message' => 'Method not set'], 'httpCode' => 400];
        }

        if (!in_array(strtolower($method), ['post', 'put'])) {
            isset($this->io) && $this->io->error('Not a POST or PUT request'); // @TODO throw exception ?
            return ['response' => ['message' => 'Not a POST or PUT request'], 'httpCode' => 400];
        }

        try {
            $huwelijk = $this->createMarriage($this->data['request'], $this->data['response']['id'] ?? null);
            $httpCode = 201;
        } catch (Exception $e) {
            $huwelijk = ['message' => $e->getMessage()];
            $httpCode = 400;
        }

        return ['response' => $huwelijk, 'httpCode' => $httpCode];
    }
}
