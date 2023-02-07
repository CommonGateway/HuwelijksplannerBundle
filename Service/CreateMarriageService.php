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

/**
 * This service holds al the logic for creating the marriage request object.
 */
class CreateMarriageService
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;
    private array $data;
    private array $configuration;

    private ObjectRepository $schemaRepo;
    private ObjectRepository $objectRepo;

    private ?Schema $huwelijkSchema;

    /**
     * @param ObjectEntityService    $objectEntityService
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
        $this->data = [];
        $this->configuration = [];

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
     * Validate huwelik type.
     *
     * @return array|bool $huwelijk OR false when invalid huwelijk
     */
    private function validateType(array $huwelijk)
    {
        if (!isset($huwelijk['type'])) {
            isset($this->io) && $this->io->error('huwelijk.type not given'); // @TODO throw exception ?
            throw new Exception('huwelijk.type not given');

            return false;
        }

        if (!$typeObject = $this->objectRepo->find($huwelijk['type'])) {
            isset($this->io) && $this->io->error('huwelijk.type not found in the databse with given id'); // @TODO throw exception ?
            throw new Exception('huwelijk.type not found in the databse with given id');

            return false;
        }

        if (!in_array($typeObject['upnLabel'], ['huwelijk', 'omzetten', 'partnerschap'])) {
            isset($this->io) && $this->io->error('huwelijk.type is not huwelijk, omzetten or partnerschap'); // @TODO throw exception ?
            throw new Exception('huwelijk.type is not huwelijk, omzetten or partnerschap');

            return false;
        }
    }

    /**
     * Validate huwelik type.
     *
     * @return array|bool $huwelijk OR false when invalid huwelijk
     */
    private function validateCeremonie(array $huwelijk)
    {
        if (!isset($huwelijk['type'])) {
            isset($this->io) && $this->io->error('huwelijk.type not given'); // @TODO throw exception ?
            throw new Exception('huwelijk.type not given');

            return false;
        }

        if (!$typeObject = $this->objectRepo->find($huwelijk['type'])) {
            isset($this->io) && $this->io->error('huwelijk.type not found in the databse with given id'); // @TODO throw exception ?
            throw new Exception('huwelijk.type not found in the databse with given id');

            return false;
        }

        if (!in_array($typeObject['upnLabel'], ['flitshuwelijk', 'gratis huwelijk', 'eenvoudig huwelijk', 'uitgebreid huwelijk'])) {
            isset($this->io) && $this->io->error('huwelijk.type is not huwelijk, omzetten or partnerschap'); // @TODO throw exception ?
            throw new Exception('huwelijk.type is not huwelijk, omzetten or partnerschap');

            return false;
        }
    }

    /**
     * Validate the huwelijk object.
     *
     * @return array|bool $huwelijk OR false when invalid huwelijk
     */
    private function validateMarriage(array $huwelijk)
    {
        $this->validateType($huwelijk);

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
    public function createMarriageHandler(?array $data = [], ?array $configuration = [], Security $security): ?array
    {
        isset($this->io) && $this->io->success('createMarriageHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        if (!isset($this->data['request'])) {
            isset($this->io) && $this->io->error('No data passed'); // @TODO throw exception ?
            throw new Exception('No data passed');
            return $this->data;
        }

        if (!$method = $this->data['parameters']->getMethod()) {
            isset($this->io) && $this->io->error('Method not set'); // @TODO throw exception ?
            throw new Exception('Method not set');
            return $this->data;
        }

        if (!in_array(strtolower($method), ['post', 'put'])) {
            isset($this->io) && $this->io->error('Not a POST or PUT request'); // @TODO throw exception ?
            throw new Exception('Not a POST or PUT request');
            return $this->data;
        }

        if (!$this->getHuwelijkSchema()) {
            return $this->data;
        }

        $huwelijk = $this->data['request'];

        // test
        isset($this->io) && $this->io->info($security->getUser()->getUserIdentifier());

        if (isset($this->data['response']['id'])) {
            if (!$huwelijkObject = $this->objectRepo->find($this->data['response']['id'])) {
                isset($this->io) && $this->io->error('Could not find huwelijk with id ' . $this->data['response']['id']); // @TODO throw exception ?
                throw new Exception('Could not find huwelijk with id ' . $this->data['response']['id']);
            }
        } else {
            $huwelijkObject = new ObjectEntity($this->huwelijkSchema);
        }

        // $requestPartnerAssent = [
        //     'name'        => $security->getUser()->getUserIdentifier(),
        //     'description' => null,
        //     'property'    => null,
        //     'contact'     => null,
        //     'person'      => 'natuurlijk_persoon',
        //     'status'      => null,
        //     'requester'   => null,
        // ];
        $huwelijk = $this->validateMarriage($huwelijk);
        // $huwelijkObject->hydrate($huwelijk);

        return $this->data['response'];
    }
}
