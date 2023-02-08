<?php

// src/Service/InstallationService.php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Action;
use App\Entity\CollectionEntity;
use App\Entity\Cronjob;
use App\Entity\DashboardCard;
use App\Entity\Endpoint;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InstallationService implements InstallerInterface
{
    private EntityManagerInterface $entityManager;
    private ContainerInterface $container;
    private SymfonyStyle $io;

    public const OBJECTS_THAT_SHOULD_HAVE_CARDS = [
        'https://huwelijksplanner.nl/schemas/hp.assent.schema.json',
        'https://huwelijksplanner.nl/schemas/hp.calendar.schema.json',
        'https://huwelijksplanner.nl/schemas/hp.availability.schema.json',
        'https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json',
        'https://huwelijksplanner.nl/schemas/hp.medewerker.schema.json',
        'https://huwelijksplanner.nl/schemas/hp.sdgProduct.schema.json',
    ];

    public const SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS = [
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.assent.schema.json',        'path' => 'assents',        'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.calendar.schema.json',     'path' => 'calendars',     'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.availability.schema.json',      'path' => 'availabilities',      'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json',         'path' => 'huwelijk',        'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.medewerker.schema.json',       'path' => 'medewerkers',        'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.sdgProduct.schema.json',       'path' => 'producten',        'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.accommodation.schema.json',       'path' => 'accommodations',        'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.message.schema.json',       'path' => 'messages',        'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.sendList.schema.json',       'path' => 'send_lists',        'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.service.schema.json',       'path' => 'services',        'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.subscriber.schema.json',       'path' => 'subscribers',        'methods' => []],
        ['reference' => 'https://huwelijksplanner.nl/schemas/hp.availabilityCheck.schema.json',       'path' => 'calendar/availabilitycheck',        'methods' => ['POST']],
    ];

    public const SOURCES = [
               ['name' => 'Mollie API', 'location' => 'https://api.mollie.com/',
                   'headers' => ['accept' => 'application/json'], 'auth' => 'apikey', 'apikey' => '']
    ];

    public const ACTION_HANDLERS = [
        ['name' => 'CreateAvailbility', 'actionHandler' => 'CommonGateway\HuwelijksplannerBundle\ActionHandler\CreateAvailabilityHandler', 'listens' => ['huwelijksplanner.calendar.listens'], 'conditions' => [[1 => 1]]],
        ['name' => 'CreateMarriage', 'actionHandler' => 'CommonGateway\HuwelijksplannerBundle\ActionHandler\CreateMarriageHandler', 'listens' => ['huwelijksplanner.huwelijk.created']],
        ['name' => 'HandleAssent', 'actionHandler' => 'CommonGateway\HuwelijksplannerBundle\ActionHandler\HandleAssentHandler', 'listens' => ['huwelijksplanner.default.listens']],
        ['name' => 'UpdateChecklist', 'actionHandler' => 'CommonGateway\HuwelijksplannerBundle\ActionHandler\UpdateChecklistHandler', 'listens' => ['huwelijksplanner.default.listens'], 'conditions' => [[1 => 1]]],
    ];

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
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

    public function install()
    {
        $this->checkDataConsistency();
    }

    public function update()
    {
        $this->checkDataConsistency();
    }

    public function uninstall()
    {
        // Do some cleanup
    }

    /**
     * This function creates default configuration for the action.
     *
     * @param $actionHandler The actionHandler for witch the default configuration is set
     *
     * @return array
     */
    public function addActionConfiguration($actionHandler): array
    {
        $defaultConfig = [];

        // What if there are no properties?
        if (!isset($actionHandler->getConfiguration()['properties'])) {
            return $defaultConfig;
        }

        foreach ($actionHandler->getConfiguration()['properties'] as $key => $value) {
            switch ($value['type']) {
                case 'string':
                case 'array':
                    if (key_exists('example', $value)) {
                        $defaultConfig[$key] = $value['example'];
                    }
                    break;
                case 'object':
                    break;
                case 'uuid':
                    if (isset($value['$ref'])) {
                        try {
                            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $value['$ref']]);
                        } catch (Exception $exception) {
                            throw new Exception("No entity found with reference {$value['$ref']}");
                        }
                        $defaultConfig[$key] = $entity->getId()->toString();
                    }
                    break;
                default:
                    return $defaultConfig;
            }
        }

        return $defaultConfig;
    }

    /**
     * Decides wether or not an array is associative.
     *
     * @param array $array The array to check
     *
     * @return bool Wether or not the array is associative
     */
    private function isAssociative(array $array): bool
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param array $defaultConfig
     * @param array $overrides
     *
     * @throws Exception
     *
     * @return array
     */
    public function overrideConfig(array $defaultConfig, array $overrides): array
    {
        foreach ($overrides as $key => $override) {
            if (is_array($override) && $this->isAssociative($override)) {
                $defaultConfig[$key] = $this->overrideConfig(isset($defaultConfig[$key]) ? $defaultConfig[$key] : [], $override);
            } elseif ($key == 'entity') {
                $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $override]);
                if (!$entity) {
                    throw new Exception("No entity found with reference {$override}");
                }
                $defaultConfig[$key] = $entity->getId()->toString();
            } elseif ($key == 'source') {
                $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['name' => $override]);
                if (!$source) {
                    throw new Exception("No source found with name {$override}");
                }
                $defaultConfig[$key] = $source->getId()->toString();
            } else {
                $defaultConfig[$key] = $override;
            }
        }

        return $defaultConfig;
    }

    /**
     * This function creates actions for all the actionHandlers in OpenCatalogi.
     *
     * @return void
     */
    public function addActions(): void
    {
        $actionHandlers = $this::ACTION_HANDLERS;
        (isset($this->io) ? $this->io->writeln(['', '<info>Looking for actions</info>']) : '');

        foreach ($actionHandlers as $handler) {
            $actionHandler = $this->container->get($handler['actionHandler']);

            if (array_key_exists('name', $handler)) {
                if ($this->entityManager->getRepository('App:Action')->findOneBy(['name'=> $handler['name']])) {
                    (isset($this->io) ? $this->io->writeln(['Action found with name '.$handler['name']]) : '');
                    continue;
                }
            } elseif ($this->entityManager->getRepository('App:Action')->findOneBy(['class'=> get_class($actionHandler)])) {
                (isset($this->io) ? $this->io->writeln(['Action found for '.$handler['actionHandler']]) : '');
                continue;
            }

            if (!$actionHandler->getConfiguration()) {
                continue;
            }

            $defaultConfig = $this->addActionConfiguration($actionHandler);
            isset($handler['config']) && $defaultConfig = $this->overrideConfig($defaultConfig, $handler['config']);

            $action = new Action($actionHandler);
            array_key_exists('name', $handler) ? $action->setName($handler['name']) : '';
            $action->setListens($handler['listens'] ?? ['huwelijksplanner.default.listens']);
            $action->setConfiguration($defaultConfig);
            $action->setConditions($handler['conditions'] ?? ['==' => [1, 1]]);

            $this->entityManager->persist($action);
            (isset($this->io) ? $this->io->writeln(['Created Action '.$action->getName().' with Handler: '.$handler['actionHandler']]) : '');
        }
    }

    /**
     * Creates the huwelijksplanner Endpoints from the given array.
     *
     * @param array $objectsThatShouldHaveEndpoints
     *
     * @return array
     */
    private function createEndpoints(array $objectsThatShouldHaveEndpoints): array
    {
        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');
        $endpoints = [];
        foreach ($objectsThatShouldHaveEndpoints as $objectThatShouldHaveEndpoint) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $objectThatShouldHaveEndpoint['reference']]);
            if ($entity instanceof Entity && !$endpointRepository->findOneBy(['name' => $entity->getName()])) {
                $endpoint = new Endpoint($entity, $objectThatShouldHaveEndpoint['path'], $objectThatShouldHaveEndpoint['methods']);

                if ($objectThatShouldHaveEndpoint['reference'] == 'https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json') {
                    $endpoint->setThrows(['huwelijksplanner.huwelijk.created']);
                } elseif ($objectThatShouldHaveEndpoint['reference'] == 'https://huwelijksplanner.nl/schemas/hp.availabilityCheck.schema.json') {
                    $endpoint->setThrows(['huwelijksplanner.calendar.listens']);
                    $endpoint->removeEntity($entity);
                }

                $this->entityManager->persist($endpoint);
                $this->entityManager->flush();
                $endpoints[] = $endpoint;
            }
        }

        (isset($this->io) ? $this->io->writeln(count($endpoints).' Endpoints Created') : '');

        return $endpoints;
    }

    /**
     * Creates the huwelijksplanner Sources.
     *
     * @param array $sourcesThatShouldExist
     *
     * @return array
     */
    private function createSources(array $sourcesThatShouldExist): array
    {
        $sourceRepository = $this->entityManager->getRepository('App:Gateway');
        $sources = [];

        foreach ($sourcesThatShouldExist as $sourceThatShouldExist) {
            if (!$sourceRepository->findOneBy(['name' => $sourceThatShouldExist['name']])) {
                $source = new Source($sourceThatShouldExist);
                $source->setApikey(array_key_exists('apikey', $sourceThatShouldExist) ? $sourceThatShouldExist['apikey'] : '');

                $this->entityManager->persist($source);
                $this->entityManager->flush();
                $sources[] = $source;
            }
        }

        (isset($this->io) ? $this->io->writeln(count($sources).' Sources Created') : '');

        return $sources;
    }

    /**
     * Adds schemas with the given prefix to the given collection.
     *
     * @param CollectionEntity $collection
     * @param string           $schemaPrefix
     *
     * @return CollectionEntity
     */
    private function addSchemasToCollection(CollectionEntity $collection, string $schemaPrefix): CollectionEntity
    {
        $entities = $this->entityManager->getRepository('App:Entity')->findByReferencePrefix($schemaPrefix);
        foreach ($entities as $entity) {
            $entity->addCollection($collection);
        }

        return $collection;
    }

    /**
     * Creates collections for huwelijkplanner.
     *
     * @return array
     */
    private function createCollections(): array
    {
        $collectionConfigs = [
            [
                'name'         => 'Huwelijksplanner',
                'prefix'       => 'hp',
                'schemaPrefix' => 'https://huwelijksplanner.nl',
            ],
        ];
        $collections = [];
        foreach ($collectionConfigs as $collectionConfig) {
            $collectionsFromEntityManager = $this->entityManager->getRepository('App:CollectionEntity')->findBy(['name' => $collectionConfig['name']]);
            if (count($collectionsFromEntityManager) == 0) {
                $collection = new CollectionEntity($collectionConfig['name'], $collectionConfig['prefix'], 'HuwelijksplannerBundle');
            } else {
                $collection = $collectionsFromEntityManager[0];
            }
            $collection = $this->addSchemasToCollection($collection, $collectionConfig['schemaPrefix']);
            $this->entityManager->persist($collection);
            $this->entityManager->flush();
            $collections[$collectionConfig['name']] = $collection;
        }
        (isset($this->io) ? $this->io->writeln(count($collections).' Collections Created') : '');

        return $collections;
    }

    /**
     * Creates dashboard cards for the given schemas.
     *
     * @return void
     */
    public function createDashboardCards($objectsThatShouldHaveCards): void
    {
        foreach ($objectsThatShouldHaveCards as $object) {
            isset($this->io) && $this->io->writeln('Looking for a dashboard card for: '.$object);
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object]);
            if (
                !$dashboardCard = $this->entityManager->getRepository('App:DashboardCard')->findOneBy(['entityId' => $entity->getId()])
            ) {
                $dashboardCard = new DashboardCard();
                $dashboardCard->setType('schema');
                $dashboardCard->setEntity('App:Entity');
                $dashboardCard->setObject('App:Entity');
                $dashboardCard->setName($entity->getName());
                $dashboardCard->setDescription($entity->getDescription());
                $dashboardCard->setEntityId($entity->getId());
                $dashboardCard->setOrdering(1);
                $this->entityManager->persist($dashboardCard);
                isset($this->io) && $this->io->writeln('Dashboard card created');
                continue;
            } else {
                isset($this->io) && $this->io->writeln('Entity with reference '.$object.' can\'t be found');
            }
            isset($this->io) && $this->io->writeln('Dashboard card found');
        }
    }

    /**
     * Creates cronjobs for huwelijksplanner.
     *
     * @return void
     */
    public function createCronjobs(): void
    {
        (isset($this->io) ? $this->io->writeln(['', '<info>Looking for cronjobs</info>']) : '');
        // We only need 1 cronjob so lets set that
        if (!$cronjob = $this->entityManager->getRepository('App:Cronjob')->findOneBy(['name' => 'Huwelijksplanner'])) {
            $cronjob = new Cronjob();
            $cronjob->setName('Huwelijksplanner');
            $cronjob->setDescription('This cronjob fires all the huwelijksplanner actions ever 5 minutes');
            $cronjob->setThrows(['huwelijksplanner.default.listens']);
            $cronjob->setIsEnabled(true);

            $this->entityManager->persist($cronjob);

            (isset($this->io) ? $this->io->writeln(['', 'Created a cronjob for '.$cronjob->getName()]) : '');
        } else {
            (isset($this->io) ? $this->io->writeln(['', 'There is alreade a cronjob for '.$cronjob->getName()]) : '');
        }
    }

    /**
     * This function installs the huwelijksplanner bundle assets.
     *
     * @return void
     */
    public function checkDataConsistency(): void
    {
        // Lets create some genneric dashboard cards
        $this->createDashboardCards($this::OBJECTS_THAT_SHOULD_HAVE_CARDS);

        // create collection prefix
        $this->createCollections();

        // cretae endpoints
        $this->createEndpoints($this::SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS);

        // create cronjobs
        $this->createCronjobs();

        // create actions from the given actionHandlers
        $this->addActions();

        // create sources
        $this->createSources($this::SOURCES);

        $this->entityManager->flush();
    }
}
