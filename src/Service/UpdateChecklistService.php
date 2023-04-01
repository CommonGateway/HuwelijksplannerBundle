<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

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
     * @var GatewayResourceService
     */
    private GatewayResourceService $gatewayResourceService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * The cache service
     *
     * @var CacheService
     */
    private CacheService $cacheService;

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
     * @param GatewayResourceService $gatewayResourceService The Gateway Resource Service
     * @param LoggerInterface        $pluginLogger           The Logger Interface
     * @param CacheService           $cacheService           The Cache Service.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GatewayResourceService $gatewayResourceService,
        LoggerInterface $pluginLogger,
        CacheService $cacheService
    ) {
        $this->entityManager          = $entityManager;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->pluginLogger           = $pluginLogger;
        $this->cacheService           = $cacheService;
        $this->data                   = [];
        $this->configuration          = [];

    }//end __construct()


    /**
     * Checks the partners of the huwelijk.
     *
     * @param ObjectEntity $huwelijk  The huwelijk object
     * @param array        $checklist The checklist array
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
        }//end if

        if ($partnersCount > 2) {
            $checklist['partners'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap kunnen maximaal 2 partners worden opgegeven',
            ];

            return $checklist;
        }//end if

        $checklist['partners'] = [
            'result'  => true,
            'display' => 'Partners zijn opgegeven',
        ];

        return $checklist;

    }//end checkHuwelijkPartners()


    /**
     * Checks the witnesses of the huwelijk.
     *
     * @param ObjectEntity $huwelijk  The huwelijk object
     * @param array        $checklist The checklist array
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
        }//end if

        if ($witnessCount > 4) {
            $checklist['getuigen'] = [
                'result'  => false,
                'display' => 'Voor een huwelijk/partnerschap kunnen maximaal 4 getuigen worden opgegeven',
            ];

            return $checklist;
        }//end if

        $checklist['getuigen'] = [
            'result'  => true,
            'display' => 'Getuigen zijn opgegeven',
        ];

        return $checklist;

    }//end checkHuwelijkWitnesses()


    /**
     * Checks the offeser of the huwelijk.
     *
     * @param ObjectEntity $huwelijk  The huwelijk object
     * @param array        $checklist The checklist array
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
        }//end if

        $checklist['ambtenaar'] = [
            'result'  => true,
            'display' => 'Ambtenaar is opgegeven',
        ];

        return $checklist;

    }//end checkHuwelijkOfficer()


    /**
     * Checks the moment of the huwelijk.
     *
     * @param ObjectEntity $huwelijk  The huwelijk object
     * @param array        $checklist The checklist array
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
        }//end if

        $checklist['moment'] = [
            'result'  => true,
            'display' => 'Moment is opgegeven',
        ];

        return $checklist;

    }//end checkHuwelijkMoment()


    /**
     * Checks the products of the huwelijk.
     *
     * @param ObjectEntity $huwelijk  The huwelijk object
     * @param array        $checklist The checklist array
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
        }//end if

        $checklist['producten'] = [
            'result'  => true,
            'display' => 'Producten zijn opgegeven',
        ];

        return $checklist;

    }//end checkHuwelijkProducts()


    /**
     * Checks the order of the huwelijk.
     *
     * @param ObjectEntity $huwelijk  The huwelijk object
     * @param array        $checklist The checklist array
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
        }//end if

        $checklist['order'] = [
            'result'  => true,
            'display' => 'Order is opgegegeven',
        ];

        return $checklist;

    }//end checkHuwelijkOrder()


    /**
     * Checks the case of the huwelijk.
     *
     * @param ObjectEntity $huwelijk  The huwelijk object
     * @param array        $checklist The checklist array
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
        }//end if

        $checklist['zaak'] = [
            'result'  => true,
            'display' => 'Zaak is opgegeven',
        ];

        return $checklist;

    }//end checkHuwelijkCase()


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
            $checklistSchema = $this->gatewayResourceService->getSchema('https://huwelijksplanner.nl/schemas/hp.checklist.schema.json', 'common-gateway/huwelijksplanner-bundle');
            $checklistObject = new ObjectEntity($checklistSchema);
        }//end if

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

        $this->cacheService->cacheObject($huwelijk);

        return $huwelijk;

    }//end checkHuwelijk()


}//end class
