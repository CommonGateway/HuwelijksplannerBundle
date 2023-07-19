<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
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
class InvitePartnerService
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

    private CacheService $cacheService;


    /**
     * @param EntityManagerInterface $entityManager          The Entity Manager
     * @param GatewayResourceService $gatewayResourceService The Gateway Resource Service
     * @param HandleAssentService    $handleAssentService    The Handle Assent Service
     * @param UpdateChecklistService $updateChecklistService The Update Checklist Service
     * @param Security               $security               The Security
     * @param LoggerInterface        $pluginLogger           The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GatewayResourceService $gatewayResourceService,
        HandleAssentService $handleAssentService,
        UpdateChecklistService $updateChecklistService,
        Security $security,
        LoggerInterface $pluginLogger,
        AssentService $assentService,
        CacheService $cacheService
    ) {
        $this->entityManager          = $entityManager;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->data                   = [];
        $this->configuration          = [];
        $this->handleAssentService    = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security               = $security;
        $this->pluginLogger           = $pluginLogger;
        $this->assentService          = $assentService;
        $this->cacheService           = $cacheService;

    }//end __construct()


    /**
     * This function creates the email and sms data.
     *
     * @param ObjectEntity $requester      The requester object.
     * @param ObjectEntity $person         The person object.
     * @param ObjectEntity $huwelijkObject The huwelijk object.
     *
     * @return ?array The updated huwelijk object as array.
     */
    private function createEmailAndSmsData(ObjectEntity $requester, ObjectEntity $person, ObjectEntity $huwelijkObject): ?array
    {
        $requesterNaam = $requester->getValue('voornaam').' '.$requester->getValue('achternaam');
        $partnerNaam   = $person->getValue('voornaam').' '.$person->getValue('achternaam');

        if ($huwelijkObject->getValue('moment') !== false
            && $huwelijkObject->getValue('locatie') !== false
        ) {
            $moment      = new DateTime($huwelijkObject->getValue('moment'));
            $description = 'Op '.$moment->format('D, d M Y H:i:s').' in '.$huwelijkObject->getValue('locatie')->getValue('upnLabel').'. ';
        }

        $dataArray['response']        = [
            'requesterNaam'     => $requesterNaam,
            'partnerNaam'       => $partnerNaam,
            'assentNaam'        => 'U bent gevraagd door '.$requesterNaam.' om te trouwen.',
            'assentDescription' => $description ?? null.$requesterNaam.' heeft gevraagd of u dit huwelijk wilt bevestigen.',
        ];
        $dataArray['response']['url'] = 'https://utrecht-huwelijksplanner.frameless.io/en/voorgenomen-huwelijk/partner/login?assentId=';

        return $dataArray;

    }//end createEmailAndSmsData()


    /**
     * This function gets the partner details from the given bsn.
     *
     * @param ObjectEntity $huwelijkObject The huwelijk from the request.
     * @param array        $huwelijk       The body from the request.
     * @param string       $bsn            The bsn of the given partner via email.
     *
     * @return array The partner datails as array.
     */
    private function invitePartnerLogin(ObjectEntity $huwelijkObject, array $huwelijk, string $bsn): array
    {
        $brpSchema      = $this->gatewayResourceService->getSchema('https://vng.brp.nl/schemas/brp.ingeschrevenPersoon.schema.json', 'common-gateway/huwelijksplanner-bundle');
        $partnerDetails = [];

        $brpPersons = $this->cacheService->searchObjects(null, ['burgerservicenummer' => $bsn], [$brpSchema->getId()->toString()])['results'];
        if (count($brpPersons) === 1) {
            $partnerDetails['brpPerson'] = $this->entityManager->find('App:ObjectEntity', $brpPersons[0]['_self']['id']);
        }//end if

        foreach ($huwelijkObject->getValue('partners') as $huwelijkPartner) {
            if ($bsn === $huwelijkPartner->getValue('contact')->getValue('subjectIdentificatie')->getValue('inpBsn')) {
                $partnerDetails['personAssent'] = $huwelijkPartner;
                $partnerDetails['person']       = $this->assentService->createPerson($huwelijk, $partnerDetails['brpPerson'], $huwelijkPartner->getValue('contact'));
            }//end if

            if ($bsn !== $huwelijkPartner->getValue('contact')->getValue('subjectIdentificatie')->getValue('inpBsn')) {
                $partnerDetails['partner'] = $huwelijkPartner->getValue('contact');
            }//end if
        }//end foreach

        return $partnerDetails;

    }//end invitePartnerLogin()


    /**
     * This function gets the partner details from the given bsn.
     *
     * @param ObjectEntity $huwelijkObject The huwelijk from the request.
     *
     * @return array The partner datails as array.
     */
    private function invitePartnerInvite(ObjectEntity $huwelijkObject): array
    {
        $partnerDetails = [];

        foreach ($huwelijkObject->getValue('partners') as $huwelijkPartner) {
            if ($huwelijkPartner->getValue('contact')->getValue('subjectIdentificatie') === false) {
                $partnerDetails['personAssent'] = $huwelijkPartner;
                $partnerDetails['person']       = $huwelijkPartner->getValue('contact');
            }

            if (($subjectIdentificatie = $huwelijkPartner->getValue('contact')->getValue('subjectIdentificatie')) !== false) {
                if ($subjectIdentificatie->getValue('inpBsn') !== false) {
                    $partnerDetails['partner'] = $huwelijkPartner->getValue('contact');
                }
            }
        }//end foreach

        return $partnerDetails;

    }//end invitePartnerInvite()


    /**
     * This function validates and creates the huwelijk object and creates an assent for the current user.
     *
     * @param array  $huwelijk The huwelijk array from the request.
     * @param string $id       The id of the huwelijk object.
     *
     * @return ?array The updated huwelijk object as array.
     */
    private function invitePartner(array $huwelijk, string $id): ?array
    {
        $huwelijkObject = $this->entityManager->getRepository('App:ObjectEntity')->find($id);
        if ($huwelijkObject instanceof ObjectEntity === false) {
            $this->pluginLogger->error('Could not find huwelijk with id '.$id);

            $this->data['response'] = 'Could not find huwelijk with id '.$id;

            return $this->data;
        }//end if

        // @TODO check if the requester has already a partner
        // if so throw error else continue
        if (count($huwelijk['partners']) !== 1
        ) {
            return $this->data;
        }

        if (count($huwelijkObject->getValue('partners')) > 2) {
            // @TODO update partner?
            return $huwelijkObject->toArray();
        }//end if

        if (isset($huwelijk['partners'][0]['contact']['subjectIdentificatie']['inpBsn']) === true) {
            $partnerDetails = $this->invitePartnerLogin($huwelijkObject, $huwelijk, $huwelijk['partners'][0]['contact']['subjectIdentificatie']['inpBsn']);
        }//end if

        if (isset($huwelijk['partners'][0]['contact']['subjectIdentificatie']['inpBsn']) === false) {
            $partnerDetails = $this->invitePartnerInvite($huwelijkObject);
        }//end if

        $this->entityManager->flush();

        $dataArray = $this->createEmailAndSmsData($partnerDetails['partner'], $partnerDetails['person'], $huwelijkObject);

        $assent = $this->handleAssentService->handleAssent($partnerDetails['person'], 'partner', $dataArray, $huwelijkObject->getId()->toString(), $partnerDetails['personAssent']);

        $assent->setValue('name', $dataArray['response']['assentNaam']);
        $assent->setValue('description', $dataArray['response']['assentDescription']);
        $this->entityManager->persist($assent);

        $this->entityManager->persist($huwelijkObject);
        $this->entityManager->flush();

        return $this->cacheService->getObject($id);

    }//end invitePartner()


    /**
     * Creates the marriage request object.
     *
     * @param ?array $data          The data array.
     * @param ?array $configuration The configuration array.
     *
     * @return array The data array
     * @throws Exception
     */
    public function invitePartnerHandler(?array $data=[], ?array $configuration=[]): array
    {
        $this->pluginLogger->debug('invitePartnerHandler triggered');
        $this->data          = $data;
        $this->configuration = $configuration;

        $response = json_decode($this->data['response']->getContent(), true);
        $huwelijk = $this->invitePartner($this->data['body'], $response['_self']['id']);

        $this->data['response'] = new Response(json_encode($huwelijk), 200, ['content-type' => 'application/json']);

        return $this->data;

    }//end invitePartnerHandler()


}//end class
