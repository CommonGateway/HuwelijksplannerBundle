<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
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
        LoggerInterface $pluginLogger
    ) {
        $this->entityManager          = $entityManager;
        $this->gatewayResourceService = $gatewayResourceService;
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

        $dataArray = $this->createEmailAndSmsData($huwelijkObject);
        // creates an assent and add the person to the partners of this merriage
        $assent = $this->handleAssentService->handleAssent($person, 'witness', $dataArray, $huwelijkObject->getId()->toString(), $witness);

        $assent->setValue('name', $dataArray['response']['assentNaam']);
        $assent->setValue('description', $dataArray['response']['assentDescription']);
        $this->entityManager->persist($assent);
        $this->entityManager->flush();

    }//end updateWitness()


    /**
     * This function creates the email and sms data.
     *
     * @param ObjectEntity $huwelijkObject The huwelijk object.
     *
     * @return ?array The updated huwelijk object as array.
     */
    private function createEmailAndSmsData(ObjectEntity $huwelijkObject): ?array
    {
        $partnersAssents = $huwelijkObject->getValue('partners');

        if (count($partnersAssents) === 2) {
            $requesterNaam = $partnersAssents[0]->getValue('contact')->getValue('voornaam').' '.$partnersAssents[0]->getValue('contact')->getValue('achternaam');
            $partnerNaam   = $partnersAssents[1]->getValue('contact')->getValue('voornaam').' '.$partnersAssents[1]->getValue('contact')->getValue('achternaam');

            if ($huwelijkObject->getValue('moment') !== false
                && $huwelijkObject->getValue('locatie') !== false
            ) {
                $moment      = new DateTime($huwelijkObject->getValue('moment'));
                $description = 'Op '.$moment->format('D, d M Y H:i:s').' in '.$huwelijkObject->getValue('locatie')->getValue('upnLabel').'. ';
            }

            $dataArray['response'] = [
                'requesterNaam'     => $requesterNaam,
                'partnerNaam'       => $partnerNaam,
                'assentNaam'        => 'U bent gevraagd om getuigen te zijn bij het huwelijk van '.$requesterNaam.' en '.$partnerNaam,
                'assentDescription' => $description.$requesterNaam.' & '.$partnerNaam.' hebben u gevraagd om een reactie te geven op dit verzoek.',
            ];
        }

        $dataArray['response']['url'] = 'https://utrecht-huwelijksplanner.frameless.io/en/voorgenomen-huwelijk/getuigen/instemmen?assentId=';

        return $dataArray;

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

        if (isset($huwelijk['getuigen']) === true
            && count($huwelijk['getuigen']) <= 4
        ) {
            // Update the witnesses.
            foreach ($huwelijkObject->getValue('getuigen') as $witness) {
                $this->updateWitness($witness, $huwelijkObject);
            }

            $huwelijkObject = $this->updateChecklistService->checkHuwelijk($huwelijkObject);
            $this->entityManager->persist($huwelijkObject);
            $this->entityManager->flush();
        }//end if

        $this->entityManager->persist($huwelijkObject);
        $this->entityManager->flush();

        return $huwelijkObject->toArray();

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

        $response       = json_decode($this->data['response']->getContent(), true);
        $huwelijkObject = $this->entityManager->getRepository('App:ObjectEntity')->find($response['_self']['id']);

        if (isset($this->data['body']) === false) {
            $this->pluginLogger->error('No data passed');
            $this->data['response'] = new Response(json_encode($huwelijkObject->toArray()), 200, ['content-type' => 'application/json']);

            return $this->data;
        }//end if

        if ($this->data['method'] !== 'PATCH') {
            $this->pluginLogger->error('Not a PATCH request');
            $this->data['response'] = new Response(json_encode($huwelijkObject->toArray()), 200, ['content-type' => 'application/json']);

            return $this->data;
        }//end if

        $response = json_decode($this->data['response']->getContent(), true);
        $huwelijk = $this->inviteWitness($this->data['body'], $response['_self']['id']);

        $this->data['response'] = new Response(json_encode($huwelijk), 200, ['content-type' => 'application/json']);

        return $this->data;

    }//end inviteWitnessHandler()


}//end class
