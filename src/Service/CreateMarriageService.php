<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use CommonGateway\HuwelijksplannerBundle\Service\PaymentService;

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
     * @var PaymentService
     */
    private PaymentService $paymentService;

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
        PaymentService $paymentService,
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
        $this->paymentService         = $paymentService;
        $this->assentService          = $assentService;

    }//end __construct()


    /**
     * This function gets or creates a klant object.
     *
     * @param array $huwelijk The huwelijk array
     */
    private function getPerson(array $huwelijk): ObjectEntity
    {
        $brpSchema    = $this->gatewayResourceService->getSchema('https://vng.brp.nl/schemas/brp.ingeschrevenPersoon.schema.json', 'common-gateway/huwelijksplanner-bundle');
        $personSchema = $this->gatewayResourceService->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json', 'common-gateway/huwelijksplanner-bundle');

        // get brp person from the logged in user
        $brpPersons = $this->cacheService->searchObjects(null, ['burgerservicenummer' => $this->security->getUser()->getPerson()], [$brpSchema->getId()->toString()])['results'];
        $brpPerson  = null;
        if (count($brpPersons) === 1) {
            $brpPerson = $this->entityManager->find('App:ObjectEntity', $brpPersons[0]['_self']['id']);
        }//end if

        if ($brpPerson->getValue('burgerservicenummer') === false) {
            $person = $this->assentService->createPerson($huwelijk, $brpPerson);
        }

        if ($brpPerson->getValue('burgerservicenummer') !== false) {
            $persons = $this->cacheService->searchObjects(null, ['subjectIdentificatie.inpBsn' => $brpPerson->getValue('burgerservicenummer')], [$personSchema->getId()->toString()])['results'];
            if (count($persons) === 1) {
                $person = $this->entityManager->find('App:ObjectEntity', $persons[0]['_self']['id']);
            }//end if

            if (count($persons) === 0) {
                // create person from logged in user and if we have a brp person we set those values
                // if not we set the values from the security object
                $person = $this->assentService->createPerson($huwelijk, $brpPerson);
            }
        }

        return $person;

    }//end getPerson()


    /**
     * This function validates and creates the huwelijk object
     * and creates an assent for the current user.
     */
    private function createMarriage(string $huwelijkId, array $huwelijk): ?array
    {
        $huwelijkObject = $this->entityManager->find('App:ObjectEntity', $huwelijkId);

        $huwelijkArray = $huwelijkObject->toArray();

        $person = $this->getPerson($huwelijk);

        // creates an assent and add the person to the partners of this merriage
        $requesterAssent['partners'][] = $assent = $this->handleAssentService->handleAssent($person, 'requester', $this->data, $huwelijkObject->getId()->toString(), null)->getId()->toString();
        $huwelijkObject->hydrate($requesterAssent);

        $this->entityManager->persist($huwelijkObject);
        $this->entityManager->flush();
        $this->cacheService->cacheObject($huwelijkObject);
        // @todo this is hacky, the above schould alredy do this
        return $this->cacheService->getObject($huwelijkId);

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

        $response = json_decode($this->data['response']->getContent(), true);

        $huwelijk = $this->createMarriage($response['_self']['id'], $this->data['body']);

        $this->data['response'] = new Response(json_encode($huwelijk), 201, ['content-type' => 'application/json']);

        return $this->data;

    }//end createMarriageHandler()


}//end class
