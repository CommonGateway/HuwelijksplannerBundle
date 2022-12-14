<?php

// src/Service/InstallationService.php
namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Action;
use App\Entity\Cronjob;
use App\Entity\DashboardCard;
use App\Entity\Endpoint;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InstallationService implements InstallerInterface
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Set symfony style in order to output to the console
     *
     * @param SymfonyStyle $io
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
     * The actionHandlers in Kiss
     *
     * @return array
     */
    public function actionHandlers(): array
    {
        return [
            'CommonGateway\HuwelijksplannerBundle\ActionHandler\HuwelijksplannerAssentHandler',
            'CommonGateway\HuwelijksplannerBundle\ActionHandler\HuwelijksplannerHandler'
        ];
    }

    /**
     * This function creates default configuration for the action
     *
     * @param $actionHandler The actionHandler for witch the default configuration is set
     * @return array
     */
    public function addActionConfiguration($actionHandler): array
    {
        $defaultConfig = [];
        foreach ($actionHandler->getConfiguration()['properties'] as $key => $value) {

            switch ($value['type']) {
                case 'string':
                case 'array':
                    if (in_array('example', $value)) {
                        $defaultConfig[$key] = $value['example'];
                    }
                    break;
                case 'object':
                    break;
                case 'uuid':
                    if (in_array('$ref', $value) &&
                        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $value['$ref']])) {
                        $defaultConfig[$key] = $entity->getId()->toString();
                    }
                    break;
                default:
                    // throw error
            }
        }
        return $defaultConfig;
    }

    /**
     * This function creates actions for all the actionHandlers in Kiss
     *
     * @return void
     */
    public function addActions(): void
    {
        $actionHandlers = $this->actionHandlers();
        (isset($this->io)?$this->io->writeln(['','<info>Looking for actions</info>']):'');

        foreach ($actionHandlers as $handler) {
            $actionHandler = $this->container->get($handler);

            if ($this->entityManager->getRepository('App:Action')->findOneBy(['class'=> get_class($actionHandler)])) {

                (isset($this->io)?$this->io->writeln(['Action found for '.$handler]):'');
                continue;
            }

            if (!$schema = $actionHandler->getConfiguration()) {
                continue;
            }

            $defaultConfig = $this->addActionConfiguration($actionHandler);

            $action = new Action($actionHandler);
            $action->setListens(['huwelijksplanner.default.listens']);
            $action->setConfiguration($defaultConfig);

            $this->entityManager->persist($action);

            (isset($this->io)?$this->io->writeln(['Action created for '.$handler]):'');
        }
    }

    public function checkDataConsistency()
    {

        // Lets create some genneric dashboard cards
        $objectsThatShouldHaveCards = ['https://opencatalogi.nl/example.schema.json'];

        foreach ($objectsThatShouldHaveCards as $object) {
            (isset($this->io) ? $this->io->writeln('Looking for a dashboard card for: ' . $object) : '');
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
                (isset($this->io) ? $this->io->writeln('Dashboard card created') : '');
                continue;
            }
            (isset($this->io) ? $this->io->writeln('Dashboard card found') : '');
        }

        // Let create some endpoints
        $objectsThatShouldHaveEndpoints = ['https://opencatalogi.nl/example.schema.json'];

        foreach ($objectsThatShouldHaveEndpoints as $object) {
            (isset($this->io) ? $this->io->writeln('Looking for a endpoint for: ' . $object) : '');
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object]);

            if (
                count($entity->getEndpoints()) == 0
            ) {
                $endpoint = new Endpoint($entity);
                $this->entityManager->persist($endpoint);
                (isset($this->io) ? $this->io->writeln('Endpoint created') : '');
                continue;
            }
            (isset($this->io) ? $this->io->writeln('Endpoint found') : '');
        }

        // Lets see if there is a generic search endpoint

        // aanmaken van actions met een cronjob
        $this->addActions();

        (isset($this->io)?$this->io->writeln(['','<info>Looking for cronjobs</info>']):'');
        // We only need 1 cronjob so lets set that
        if(!$cronjob = $this->entityManager->getRepository('App:Cronjob')->findOneBy(['name'=>'Huwelijksplanner']))
        {
            $cronjob = new Cronjob();
            $cronjob->setName('Huwelijksplanner');
            $cronjob->setDescription("This cronjob fires all the huwelijksplanner actions ever 5 minutes");
            $cronjob->setThrows(['huwelijksplanner.default.listens']);

            $this->entityManager->persist($cronjob);

            (isset($this->io)?$this->io->writeln(['','Created a cronjob for Huwelijksplanner']):'');
        }
        else {
            (isset($this->io)?$this->io->writeln(['','There is alreade a cronjob for Huwelijksplanner']):'');
        }

        $this->entityManager->flush();
    }
}
