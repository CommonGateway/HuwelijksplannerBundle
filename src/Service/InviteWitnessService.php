<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Safe\DateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * This service holds al the logic for creating the marriage request object.
 */
class InviteWitnessService
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
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

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


    /**
     * @param EntityManagerInterface $entityManager          The Entity Manager
     * @param GatewayResourceService $gatewayResourceService The Gateway Resource Service
     * @param MappingService         $mappingService         The Mapping Servive
     * @param CacheService           $cacheService           The Cache Service
     * @param HandleAssentService    $handleAssentService    The Handle Assent Service
     * @param UpdateChecklistService $updateChecklistService The Update Checklist Service
     * @param Security               $security               The Security
     * @param LoggerInterface        $pluginLogger           The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GatewayResourceService $gatewayResourceService,
        MappingService $mappingService,
        CacheService $cacheService,
        HandleAssentService $handleAssentService,
        UpdateChecklistService $updateChecklistService,
        Security $security,
        LoggerInterface $pluginLogger
    ) {
        $this->entityManager          = $entityManager;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->mappingService         = $mappingService;
        $this->cacheService           = $cacheService;
        $this->data                   = [];
        $this->configuration          = [];
        $this->handleAssentService    = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security               = $security;
        $this->pluginLogger           = $pluginLogger;

    }//end __construct()


    /**
     * This function updates a witness from the given data.
     *
     * @param ObjectEntity $witness        The witness from the huwelijk.
     * @param ObjectEntity $huwelijkObject The huwelijks object.
     * @param array        $data           The data array with information about the marriage.
     *
     * @return void
     */
    private function updateWitness(ObjectEntity $witness, ObjectEntity $huwelijkObject): void
    {
        $person = $witness->getValue('contact');

        // creates an assent and add the person to the partners of this merriage
        $assent = $this->handleAssentService->handleAssent($person, 'witness', $huwelijkObject->getId()->toString(), $witness);

        // Create email and sms data.
        $dataArray = $this->createEmailAndSmsData($huwelijkObject, $person, $assent->getId()->toString());

        // Update assent with assentName and assentDescription of the data array.
        $assent->setValue('name', $dataArray['response']['assentName']);
        $assent->setValue('description', $dataArray['response']['assentDescription']);
        $this->entityManager->persist($assent);
        $this->entityManager->flush();

        // Hanle send email and sms for assent.
        $this->handleAssentService->handleAssentEmailAndSms($person, 'witness',  $dataArray, $assent);

    }//end updateWitness()


    /**
     * This function creates the email and sms data.
     *
     * @param ObjectEntity $huwelijkObject The huwelijk object.
     * @param ObjectEntity $person         The person object.
     * @param string $assentId The assent id of the witness.
     *
     * @return ?array The updated huwelijk object as array.
     */
    private function createEmailAndSmsData(ObjectEntity $huwelijkObject, ObjectEntity $person, string $assentId): ?array
    {
        $partnersAssents = $huwelijkObject->getValue('partners');
        if (count($partnersAssents) !== 2) {
            return [];
        }

        $moment = null;
        if ($huwelijkObject->getValue('moment') !== false) {
            $moment = $huwelijkObject->getValue('moment');
        }

        $location = null;
        if ($huwelijkObject->getValue('locatie') !== false) {
            $location = $huwelijkObject->getValue('locatie')->getValue('upnLabel');
        }

        $dataArray = [
            'requesterName' => $partnersAssents[0]->getValue('contact')->getValue('voornaam').' '.$partnersAssents[0]->getValue('contact')->getValue('achternaam'),
            'partnerName'   => $partnersAssents[1]->getValue('contact')->getValue('voornaam').' '.$partnersAssents[1]->getValue('contact')->getValue('achternaam'),
            'witnessName'   => $person->getValue('voornaam'),
            'moment'        => $moment,
            'location'      => $location,
            'huwelijk'      => $huwelijkObject,
            'assentId'      => $assentId
        ];

        $mapping = $this->gatewayResourceService->getMapping('https://huwelijksplanner.nl/mapping/hp.emailAndSmsDataWitness.mapping.json', 'common-gateway/huwelijksplanner-bundle');

        $data['response'] = $this->mappingService->mapping($mapping, $dataArray);

        return $data;
    }//end createEmailAndSmsData()


    /**
     * This function validates and creates the huwelijk object
     * and creates an assent for the current user.
     *
     * @param array                              $huwelijk The huwelijk array from the request.
     * @param string                             $id       The id of the huwelijk.
     * @param array the huwelijksobject as array.
     */
    private function inviteWitness(array $huwelijk, string $id): array
    {
        $huwelijkObject = $this->entityManager->getRepository('App:ObjectEntity')->find($id);
        if ($huwelijkObject instanceof ObjectEntity === false) {
            $this->pluginLogger->error('Could not find huwelijk with id '.$id);

            $this->data['response'] = 'Could not find huwelijk with id '.$id;

            return $this->data;
        }//end if

        if (count($huwelijk['getuigen']) <= 4
        ) {
            // Update the witnesses.
            foreach ($huwelijkObject->getValue('getuigen') as $witness) {
                $this->updateWitness($witness, $huwelijkObject);
            }
        }//end if

        $this->entityManager->persist($huwelijkObject);
        $this->entityManager->flush();

        return $this->cacheService->getObject($id);

    }//end inviteWitness()


    /**
     * Creates the marriage request object.
     *
     * @param ?array $data          The data array.
     * @param ?array $configuration The configuration array.
     *
     * @throws Exception
     *
     * @return ?array The data array.
     */
    public function inviteWitnessHandler(?array $data=[], ?array $configuration=[]): ?array
    {
        $this->pluginLogger->debug('inviteWitnessHandler triggered');
        $this->data          = $data;
        $this->configuration = $configuration;

        $response = json_decode($this->data['response']->getContent(), true);
        $huwelijk = $this->inviteWitness($this->data['body'], $response['_self']['id']);

        $this->data['response'] = new Response(json_encode($huwelijk), 200, ['content-type' => 'application/json']);

        return $this->data;

    }//end inviteWitnessHandler()


}//end class
