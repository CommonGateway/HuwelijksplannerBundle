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
    private SymfonyStyle $symfonyStyle;

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
     */
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
        $this->data = [];
        $this->configuration = [];
    }

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $symfonyStyle
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $symfonyStyle): self
    {
        $this->symfonyStyle = $symfonyStyle;

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
     * Checks the partners of the huwelijk
     *
     * @param ObjectEntity $huwelijk The huwelijk object
     * @param array $checklist The checklist array
     *
     * @return ObjectEntity The huwelijk object with updated/created checklist
     */
    public function checkHuwelijkPartners(ObjectEntity $huwelijk, array $checklist): array
    {
        // Check partners.
        $partnersCount = count($huwelijk->getValue('partners'));
        if ($partnersCount < 2) {
            $checklist['partners'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap zijn minimaal 2 partners nodig',
            ];

            return $checklist;
        }

        if ($partnersCount > 2) {
            $checklist['partners'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap kunnen maximaal 2 partners worden opgegeven',
            ];

            return $checklist;
        }

        $checklist['partners'] = [
            'result'  => true,
            'display' => 'Partners zijn opgegeven',
        ];

        return $checklist;
    }

    /**
     * Checks the witnesses of the huwelijk
     *
     * @param ObjectEntity $huwelijk The huwelijk object
     * @param array $checklist The checklist array
     *
     * @return ObjectEntity The huwelijk object with updated/created checklist
     */
    public function checkHuwelijkWitnesses(ObjectEntity $huwelijk, array $checklist): array
    {
        // Check getuigen.
        // @todo eigenlijk is het minimaal 1 en maximaal 2 getuigen per partner.
        $witnessCount = count($huwelijk->getValue('getuigen'));
        if ($witnessCount < 2) {
            $checklist['getuigen'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap zijn minimaal 2 getuigen nodig',
            ];

            return $checklist;
        }

        if ($witnessCount > 4) {
            $checklist['getuigen'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap kunnen maximaal 4 getuigen worden opgegeven',
            ];

            return $checklist;
        }

        $checklist['getuigen'] = [
            'result'  => true,
            'display' => 'Getuigen zijn opgegeven',
        ];

        return $checklist;
    }

    /**
     * Checks the offeser of the huwelijk
     *
     * @param ObjectEntity $huwelijk The huwelijk object
     * @param array $checklist The checklist array
     *
     * @return ObjectEntity The huwelijk object with updated/created checklist
     */
    public function checkHuwelijkOfficer(ObjectEntity $huwelijk, array $checklist): array
    {
        // Kijken naar ambtenaar.
        if ($huwelijk->getValue('ambtenaar') === false) {
            $checklist['ambtenaar'] = [
                'result'  => false,
                'display' => 'Nog geen ambtenaar opgegeven',
            ];

            return $checklist;
        }

        $checklist['ambtenaar'] = [
            'result'  => true,
            'display' => 'Ambtenaar is opgegeven',
        ];

        return $checklist;
    }

    /**
     * Checks the moment of the huwelijk
     *
     * @param ObjectEntity $huwelijk The huwelijk object
     * @param array $checklist The checklist array
     *
     * @return ObjectEntity The huwelijk object with updated/created checklist
     */
    public function checkHuwelijkMoment(ObjectEntity $huwelijk, array $checklist): array
    {
        // Kijken naar moment.
        // @TODO trouwdatum minimaal 2 weken groter dan aanvraag datum.
        if ($huwelijk->getValue('moment') === false) {
            $checklist['moment'] = [
                'result'  => false,
                'display' => 'Nog geen moment opgegeven',
            ];

            return $checklist;
        }

        $checklist['moment'] = [
            'result'  => true,
            'display' => 'Moment is opgegeven',
        ];

        return $checklist;
    }

    /**
     * Checks the products of the huwelijk
     *
     * @param ObjectEntity $huwelijk The huwelijk object
     * @param array $checklist The checklist array
     *
     * @return ObjectEntity The huwelijk object with updated/created checklist
     */
    public function checkHuwelijkProducts(ObjectEntity $huwelijk, array $checklist): array
    {
        // Kijken naar producten.
        $productsCount = count($huwelijk->getValue('producten'));
        if ($productsCount === 0) {
            $checklist['producten'] = [
                'result'  => false,
                'display' => 'Nog geen producten opgegeven',
            ];

            return $checklist;
        }

        $checklist['producten'] = [
            'result'  => true,
            'display' => 'Producten zijn opgegeven',
        ];

        return $checklist;
    }

    /**
     * Checks the order of the huwelijk
     *
     * @param ObjectEntity $huwelijk The huwelijk object
     * @param array $checklist The checklist array
     *
     * @return ObjectEntity The huwelijk object with updated/created checklist
     */
    public function checkHuwelijkOrder(ObjectEntity $huwelijk, array $checklist): array
    {
        // Kijken naar order.
        if ($huwelijk->getValue('order') === false) {
            $checklist['order'] = [
                'result'  => false,
                'display' => 'Nog geen order opgegeven',
            ];

            return $checklist;
        }

        $checklist['order'] = [
            'result'  => true,
            'display' => 'Order is opgegegeven',
        ];

        return $checklist;
    }

    /**
     * Checks the case of the huwelijk
     *
     * @param ObjectEntity $huwelijk The huwelijk object
     * @param array $checklist The checklist array
     *
     * @return ObjectEntity The huwelijk object with updated/created checklist
     */
    public function checkHuwelijkCase(ObjectEntity $huwelijk, array $checklist): array
    {
        // Kijken naar zaak.
        if ($huwelijk->getValue('zaak') === false) {
            $checklist['zaak'] = [
                'result'  => false,
                'display' => 'Nog geen zaak opgegeven',
            ];

            return $checklist;
        }

        $checklist['zaak'] = [
            'result'  => true,
            'display' => 'Zaak is opgegeven',
        ];

        return $checklist;
    }

    /**
     * Checks data from the marriage object and updates the associated checklist.
     *
     * @param ObjectEntity $huwelijk The huwelijk object
     *
     * @return ObjectEntity The huwelijk object with updated/created checklist
     */
    public function checkHuwelijk(ObjectEntity $huwelijk): ObjectEntity
    {
        if (($checklistObject = $huwelijk->getValue('checklist')) === false) {
            $checklistSchema = $this->getSchema('https://huwelijksplanner.nl/schemas/hp.checklist.schema.json');
            $checklistObject = new ObjectEntity($checklistSchema);
        }

        $checklist = [];

        $checklist = $this->checkHuwelijkPartners($huwelijk, $checklist);
        $checklist = $this->checkHuwelijkWitnesses($huwelijk, $checklist);
        $checklist = $this->checkHuwelijkOfficer($huwelijk, $checklist);
        $checklist = $this->checkHuwelijkMoment($huwelijk, $checklist);
        $checklist = $this->checkHuwelijkProducts($huwelijk, $checklist);
        $checklist = $this->checkHuwelijkOrder($huwelijk, $checklist);
        $checklist = $this->checkHuwelijkCase($huwelijk, $checklist);

        $checklistObject->hydrate($checklist);
        $this->entityManager->persist($checklistObject);

        $huwelijk->setValue('checklist', $checklistObject);
        $this->entityManager->persist($huwelijk);

        $this->entityManager->flush();

        return $huwelijk;
    }//end checkHuwelijk()
}
