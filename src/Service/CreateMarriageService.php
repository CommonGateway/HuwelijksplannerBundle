<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * This service holds al the logic for creating the marriage request object.
 */
class CreateMarriageService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $gatewayResourceService;

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
    private LoggerInterface $pluginLogger;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var array
     */
    private array $configuration;

    private AssentService $assentService;


    /**
     * @param EntityManagerInterface $entityManager          The Entity Manager
     * @param CacheService           $cacheService           The Cache Service
     * @param GatewayResourceService $gatewayResourceService The Gateway Resource Service
     * @param HandleAssentService    $handleAssentService    The Handle Assent Service
     * @param UpdateChecklistService $updateChecklistService The Update Checklist Service
     * @param Security               $security               The Security
     * @param LoggerInterface        $pluginLogger           The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheService $cacheService,
        GatewayResourceService $gatewayResourceService,
        HandleAssentService $handleAssentService,
        UpdateChecklistService $updateChecklistService,
        Security $security,
        LoggerInterface $pluginLogger,
        AssentService $assentService
    ) {
        $this->entityManager          = $entityManager;
        $this->cacheService           = $cacheService;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->data                   = [];
        $this->configuration          = [];
        $this->handleAssentService    = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security               = $security;
        $this->pluginLogger           = $pluginLogger;
        $this->assentService          = $assentService;

    }//end __construct()


    /**
     * Validate huwelijk type.
     */
    private function validateType(array $huwelijk)
    {
        if (isset($huwelijk['type'])) {
            if (!$typeProductObject = $this->entityManager->getRepository('App:ObjectEntity')->find($huwelijk['type'])) {
                $this->pluginLogger->error('huwelijk.type not found in the databse with given id');

                return [
                    'response' => ['message' => 'huwelijk.type not found in the databse with given id'],
                    'httpCode' => 400,
                ];
            }//end if

            // @TODO check upnLabel or upnUri
            if (!in_array($typeProductObject->getValue('upnLabel'), ['huwelijk', 'Omzetting', 'Partnerschap'])) {
                $this->pluginLogger->error('huwelijk.type.upnLabel is not huwelijk, omzetten or partnerschap');

                return [
                    'response' => ['message' => 'huwelijk.type.upnLabel is not huwelijk, Omzetting or Partnerschap'],
                    'httpCode' => 400,
                ];
            }//end if

            return true;
        } else {
            $this->pluginLogger->error('huwelijk.type is not given');

            return [
                'response' => ['message' => 'huwelijk.type is not given'],
                'httpCode' => 400,
            ];
        }//end if

    }//end validateType()


    /**
     * Validate huwelijk type.
     *
     * @return array|bool $huwelijk OR false when invalid huwelijk
     */
    private function validateCeremonie(array $huwelijk)
    {
        if (isset($huwelijk['ceremonie'])) {
            if (!$ceremonieProductObject = $this->entityManager->getRepository('App:ObjectEntity')->find($huwelijk['ceremonie'])) {
                $this->pluginLogger->error('huwelijk.ceremonie not found in the databse with given id');

                return [
                    'response' => ['message' => 'huwelijk.ceremonie not found in the databse with given id'],
                    'httpCode' => 400,
                ];
            }//end if

            if (!in_array($ceremonieProductObject->getValue('upnLabel'), ['gratis trouwen', 'flits/baliehuwelijk', 'eenvoudig huwelijk', 'uitgebreid huwelijk'])) {
                $this->pluginLogger->error('huwelijk.ceremonie.upnLabel is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk');

                return [
                    'response' => ['message' => 'huwelijk.ceremonie.upnLabel is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk'],
                    'httpCode' => 400,
                ];
            }//end if

            return true;
        } else {
            $this->pluginLogger->error('huwelijk.ceremonie is not given');

            return [
                'response' => ['message' => 'huwelijk.ceremonie is not given'],
                'httpCode' => 400,
            ];
        }//end if

    }//end validateCeremonie()

    /**
     * Validate huwelijk type.
     */
    private function calculatePrice(ObjectEntity $sdgProduct, ObjectEntity $huwelijk)
    {
        // update huwelijk object with price of the ceremonie
        $vertalingen = $sdgProduct->getValue('vertalingen');
        foreach ($vertalingen as $vertaling) {
            $price = $vertaling->getValue('kostenEnBetaalmethoden');

            if ($price === 'Geen extra kosten') {
                return $huwelijk;
            }//end if

            $explodedPrice = explode(',', $price);
            $kosten        = $huwelijk->getValue('kosten');

            if ($kosten === null) {
                $amount = $kosten;
            }//end if

            if ($kosten !== null) {
                $explodedKosten = explode(' ', $kosten);

                if (count($explodedKosten) === 1) {
                    $amount = $explodedKosten[0];
                }//end if

                if (count($explodedKosten) === 2) {
                    $amount = $explodedKosten[1];
                }//end if
            }//end if

            $kosten = ($amount + $explodedPrice[0]);
            $huwelijk->setValue('kosten', 'EUR '.$kosten);
            $this->entityManager->persist($huwelijk);
            $this->entityManager->flush();

            return $huwelijk;
        }//end foreach

    }//end calculatePrice()


    /**
     * Validate huwelijk type.
     */
    private function updateMarriagePrice(ObjectEntity $huwelijk)
    {
        // @TODO has the type also has a price?
        // if (($typeObject = $huwelijk->getValue('type')) !== false){
        // $this->calculatePrice($typeObject, $huwelijk);
        // }
        if (($ceremonieObject = $huwelijk->getValue('ceremonie')) !== false) {
            $this->calculatePrice($ceremonieObject, $huwelijk);
        }

        if (($location = $huwelijk->getValue('locatie')) !== false) {
            $this->calculatePrice($location, $huwelijk);
        }

        if (($ambtenaar = $huwelijk->getValue('ambtenaar')) !== false) {
            $this->calculatePrice($ambtenaar, $huwelijk);
        }

    }//end updateMarriagePrice()


    /**
     * This function validates and creates the huwelijk object
     * and creates an assent for the current user.
     */
    private function createMarriage(string $huwelijkId, array $huwelijk): ?array
    {
        $brpSchema = $this->gatewayResourceService->getSchema('https://vng.brp.nl/schemas/brp.ingeschrevenPersoon.schema.json', 'common-gateway/huwelijksplanner-bundle');

        $huwelijkObject = $this->entityManager->find('App:ObjectEntity', $huwelijkId);

        // @TODO validate moment and location
        if ($this->validateType($huwelijk) === true
            && $this->validateCeremonie($huwelijk) === true
        ) {
            // ambtenaar en locatie
            if (key_exists('locatie', $huwelijk) === true) {
                $huwelijkArray['locatie'] = $huwelijk['locatie'];
            }//end if

            $huwelijkArray = [
                'type'      => $huwelijk['type'],
                'moment'    => $huwelijk['moment'],
                'ceremonie' => $huwelijk['ceremonie'],
                'ambtenaar' => $huwelijk['ambtenaar'],
            ];

            // @TODO hier een functie aanroepen om de kosten te bereken
            $huwelijkObject->hydrate($huwelijkArray);
            $this->entityManager->persist($huwelijkObject);
            $this->entityManager->flush();

            $this->updateMarriagePrice($huwelijkObject);

            // get brp person from the logged in user
            $brpPersons = $this->cacheService->searchObjects(null, ['burgerservicenummer' => $this->security->getUser()->getPerson()], [$brpSchema->getId()->toString()])['results'];
            $brpPerson  = null;
            if (count($brpPersons) === 1) {
                $brpPerson = $this->entityManager->find('App:ObjectEntity', $brpPersons[0]['_self']['id']);
            }//end if

            // create person from logged in user and if we have a brp person we set those values
            // if not we set the values from the security object
            $person = $this->assentService->createPerson($huwelijk, $brpPerson);

            // creates an assent and add the person to the partners of this merriage
            $requesterAssent['partners'][] = $assent = $this->handleAssentService->handleAssent($person, 'requester', $this->data)->getId()->toString();
            $huwelijkObject->hydrate($requesterAssent);

            $this->entityManager->persist($huwelijkObject);
            $this->entityManager->flush();
            $this->cacheService->cacheObject($huwelijkObject);
            // @todo this is hacky, the above schould alredy do this
            $huwelijkObject = $this->updateChecklistService->checkHuwelijk($huwelijkObject);

            return $huwelijkObject->toArray();
        }//end if

        return [
            'response' => ['message' => 'Validation failed'],
            'httpCode' => 400,
        ];

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
    public function createMarriageHandler(?array $data=[], ?array $configuration=[]): ?array
    {
        $this->pluginLogger->debug('createMarriageHandler triggered');
        $this->data          = $data;
        $this->configuration = $configuration;

        if (in_array('huwelijk', $this->data['parameters']['endpoint']->getPath()) === false) {
            return $this->data;
        }//end if

        if (isset($this->data['parameters']['body']) === false) {
            $this->pluginLogger->error('No data passed');

            return $this->data;
        }//end if

        if ($this->data['parameters']['method'] !== 'POST') {
            $this->pluginLogger->error('Not a POST request');

            return $this->data;
        }//end if

        $huwelijk = $this->createMarriage($this->data['response']['id'], $this->data['parameters']['body']);

        $this->data['response'] = $huwelijk;

        return $this->data;

    }//end createMarriageHandler()


}//end class
