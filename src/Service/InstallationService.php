<?php

// src/Service/InstallationService.php
namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Entity;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InstallationService implements InstallerInterface
{

    private EntityManagerInterface $entityManager;

    private ContainerInterface $container;

    private SymfonyStyle $io;


    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->entityManager = $entityManager;
        $this->container     = $container;

    }//end __construct()


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


    public function install()
    {
        $this->checkDataConsistency();

    }//end install()


    public function update()
    {
        $this->checkDataConsistency();

    }//end update()


    public function uninstall()
    {
        // Do some cleanup
    }//end uninstall()


    /**
     * This function sets the max depth of all entities to 5.
     *
     * @return void
     */
    public function setEntityMaxDepth()
    {
        $entities = $this->entityManager->getRepository('App:Entity')->findAll();
        foreach ($entities as $entity) {
            // Unsets the persist of the availability entity and molly entity.
            if ($entity->getReference() === 'https://huwelijksplanner.nl/schemas/hp.mollie.schema.json'
                || $entity->getReference() === 'https://huwelijksplanner.nl/schemas/hp.availability.schema.json'
            ) {
                $entity->setPersist(false);
                $this->entityManager->persist($entity);
            }

            if ($entity->getReference() === 'https://vng.brp.nl/schemas/brp.ingeschrevenPersoon.schema.json'
                && $entity->getMaxDepth() !== 5
            ) {
                // Set maxDepth to 5.
                $entity->setMaxDepth(5);
                $this->entityManager->persist($entity);
            }

            if ($entity->getMaxDepth() !== 4
                && $entity->getReference() === 'https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json'
                || $entity->getReference() === 'https://huwelijksplanner.nl/schemas/hp.sdgProduct.schema.json'
            ) {
                // Set maxDepth to 4.
                $entity->setMaxDepth(4);
                $this->entityManager->persist($entity);
            }
        }//end foreach

    }//end setEntityMaxDepth()


    /**
     * This function installs the huwelijksplanner bundle assets.
     *
     * @return void
     */
    public function checkDataConsistency(): void
    {
        // @TODO check if it works only for assent/person object
        // set all entity maxDepth to 5
        $this->setEntityMaxDepth();

        $this->entityManager->flush();

    }//end checkDataConsistency()


}//end class
