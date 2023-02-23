<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This service holds al the logic for checksing data from the marriage request and updating the associated checklist.
 */
class UpdateChecklistService
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
     * @var array
     */
    private array $data;

    /**
     * @var array
     */
    private array $configuration;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
     * Checks data from the marriage object and updates the associated checklist.
     *
     * @param ObjectEntity $huwelijk
     *
     * @return ObjectEntity
     */
    public function checkHuwelijk(ObjectEntity $huwelijk): ObjectEntity
    {
        if (!$checklistObject = $huwelijk->getValue('checklist')) {
            $checklistSchema = $this->getSchema('https://huwelijksplanner.nl/schemas/hp.checklist.schema.json');
            $checklistObject = new ObjectEntity($checklistSchema);
        }

        $checklist = [];

        // Check partners
        if (count($huwelijk->getValue('partners')) < 2) {
            $checklist['partners'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap zijn minimaal 2 partners nodig',
            ];
        } elseif (count($huwelijk->getValue('partners')) > 2) {
            $checklist['partners'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap kunnen maximaal 2 partners worden opgegeven',
            ];
        } else {
            $checklist['partners'] = [
                'result'  => true,
                'display' => 'Partners zijn opgegeven',
            ];
        }
        // Check getuigen
        // @todo eigenlijk is het minimaal 1 en maximaal 2 getuigen per partner
        if (count($huwelijk->getValue('getuigen')) < 2) {
            $checklist['getuigen'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap zijn minimaal 2 getuigen nodig',
            ];
        } elseif (count($huwelijk->getValue('getuigen')) > 4) {
            $checklist['getuigen'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap kunnen maximaal 4 getuigen worden opgegeven',
            ];
        } else {
            $checklist['getuigen'] = [
                'result'  => true,
                'display' => 'Getuigen zijn opgegeven',
            ];
        }

        // Kijken naar ambtenaar
        if (!$huwelijk->getValue('ambtenaar')) {
            $checklist['ambtenaar'] = [
                'result'  => false,
                'display' => 'Nog geen ambtenaar opgegeven',
            ];
        } else {
            $checklist['ambtenaar'] = [
                'result'  => true,
                'display' => 'Ambtenaar is opgegeven',
            ];
        }

        // Kijken naar moment
        // @TODO trouwdatum minimaal 2 weken groter dan aanvraag datum
        if (!$huwelijk->getValue('moment')) {
            $checklist['moment'] = [
                'result'  => false,
                'display' => 'Nog geen moment opgegeven',
            ];
        } else {
            $checklist['moment'] = [
                'result'  => true,
                'display' => 'Moment is opgegeven',
            ];
        }

        // Kijken naar producten
        if (!count($huwelijk->getValue('producten')) > 1) {
            $checklist['producten'] = [
                'result'  => false,
                'display' => 'Nog geen producten opgegeven',
            ];
        } else {
            $checklist['producten'] = [
                'result'  => true,
                'display' => 'Producten zijn opgegeven',
            ];
        }

        // Kijken naar order
        if (!$huwelijk->getValue('order')) {
            $checklist['order'] = [
                'result'  => false,
                'display' => 'Nog geen order opgegeven',
            ];
        } else {
            $checklist['order'] = [
                'result'  => true,
                'display' => 'Order is opgegegeven',
            ];
        }

        // Kijken naar zaak
        if (!$huwelijk->getValue('zaak')) {
            $checklist['zaak'] = [
                'result'  => false,
                'display' => 'Nog geen zaak opgegeven',
            ];
        } else {
            $checklist['zaak'] = [
                'result'  => true,
                'display' => 'Zaak is opgegeven',
            ];
        }

        $checklistObject->hydrate($checklist);
        $this->entityManager->persist($checklistObject);

        $huwelijk->setValue('checklist', $checklistObject);
        $this->entityManager->persist($huwelijk);

        $this->entityManager->flush();

        return $huwelijk;
    }

    /**
     * Checks data from the marriage request and updates the associated checklist.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     * @return array
     */
    public function updateChecklistHandler(?array $data = [], ?array $configuration = []): array
    {
        isset($this->io) && $this->io->success('updateChecklistHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        // Check if the incommming data exisits and is a huwelijk object
        // @TODO full refactor
        // if (
        //     in_array('id', $this->data) &&
        //     $huwelijk = $this->objectEntityService->getObject(null, $this->data['id']) &&
        //     $huwelijk->getEntity()->getName() == 'huwelijk'
        // ) {
        //     return $this->checkHuwelijk($huwelijk)->toArray();
        // }

        return $data;
    }
}
