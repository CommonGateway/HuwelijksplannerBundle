<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

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

                throw new GatewayException('huwelijk.type not found in the databse with given id');
            }

            if (!in_array($typeProductObject->getValue('upnLabel'), ['huwelijk', 'Omzetting', 'Partnerschap'])) {
                isset($this->io) && $this->io->error('huwelijk.type.upnLabel is not huwelijk, omzetten or partnerschap');

                throw new GatewayException('huwelijk.type.upnLabel is not huwelijk, Omzetting or Partnerschap');
            }

            return true;
        } else {
            isset($this->io) && $this->io->error('huwelijk.type is not given');

            throw new GatewayException('huwelijk.type is not given');
        }

        return true;
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
                isset($this->io) && $this->io->error('huwelijk.ceremonie not found in the databse with given id');

                throw new GatewayException('huwelijk.ceremonie not found in the databse with given id');
            }

            if (!in_array($ceremonieProductObject->getValue('upnLabel'), ['gratis trouwen', 'flits/balliehuwelijk', 'eenvoudig huwelijk', 'uitgebreid huwelijk'])) {
                isset($this->io) && $this->io->error('huwelijk.ceremonie.upnLabel is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk');

                throw new GatewayException('huwelijk.ceremonie.upnLabel is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk');
            }

            return true;
        } else {
            isset($this->io) && $this->io->error('huwelijk.ceremonie is not given');

            throw new GatewayException('huwelijk.ceremonie is not given');
        }

        return true;
    }

    /**
     * Validate the huwelijk object.
     */
    private function validateMarriage(array $huwelijk)
    {
        $this->validateType($huwelijk);
        $this->validateCeremonie($huwelijk);
    }

    /**
     * Calculates the total costs of a marriage
     */
    private function calculateCosts(array $huwelijk): array
    {
        $costs = 0; // in cents like: EUR 150 for 1.50
        isset($huwelijk['ceremonie']) && isset($huwelijk['vertalingen']['kostenEnBetaalmethoden']) && $costs += intval($huwelijk['vertalingen']['kostenEnBetaalmethoden']);


        $huwelijk['kosten'] = "EUR {strval($costs)}";
        return $huwelijk;
    }

    private function createMarriage(array $huwelijk, ?string $id)
    {
        // test
        // var_dump($this->io->info($security->getUser()->getUserIdentifier()));

        if (!$huwelijkSchema = $this->getHuwelijkSchema()) {
            isset($this->io) && $this->io->error('No HuwelijkSchema found when trying to post a huwelijk');

            return null;
        }

        if (isset($this->data['response']['id'])) {
            if (!$huwelijkObject = $this->objectRepo->find($this->data['response']['id'])) {
                isset($this->io) && $this->io->error('Could not find huwelijk with id '.$this->data['response']['id']); // @TODO throw exception ?

                throw new GatewayException('Could not find huwelijk with id '.$this->data['response']['id']);
            }
        } else {
            $huwelijkObject = new ObjectEntity($huwelijkSchema);
        }
        $this->entityManager->persist($huwelijkObject);
        $this->entityManager->flush();

        if ($this->validateType($huwelijk) && $this->validateCeremonie($huwelijk)) {
            // $huwelijk = $this->handleAssentService->handleAssent($huwelijk);
            // $huwelijk = $this->updateChecklistService->updateChecklist($huwelijk);

            $huwelijk = $this->calculateCosts($huwelijk);

            if (!isset($huwelijk['message'])) {
                $huwelijkObject->hydrate($huwelijk);
                $this->entityManager->persist($huwelijkObject);
                $huwelijk = $huwelijkObject->toArray();

                return $huwelijk;
            }

            $this->entityManager->flush();
        }

        // @TODO delete the huwelijk object if validation failed
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

        $huwelijk = $this->createMarriage($this->data['request'], $this->data['response']['id'] ?? null);

        $this->data['response'] = $huwelijk;

        return $this->data;
    }
}
