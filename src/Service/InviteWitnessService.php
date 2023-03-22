<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
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
        $this->data          = [];
        $this->configuration = [];
        $this->handleAssentService    = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security     = $security;
        $this->pluginLogger = $pluginLogger;

    }//end __construct()


    /**
     * This function gets the emails of the witnesses of the marriage that were already added.
     *
     * @param ObjectEntity $huwelijkObject The huwelijksobject.
     *
     * @return array The emails of the witnesses.
     */
    private function getHuwelijkWitnessesEmails(ObjectEntity $huwelijkObject): array
    {
        $witnessAssentsEmail = [];
        foreach ($huwelijkObject->getValue('getuigen') as $witnessObject) {
            $witnessAssentPersonId = $witnessObject->getValue('contact');

            if ($witnessAssentPersonId === null) {
                continue;
            }//end if

            $witnessAssentPerson = $this->entityManager->getRepository('App:ObjectEntity')->find($witnessAssentPersonId);
            $emails = $witnessAssentPerson->getValue('emails');

            if ($emails[0] === null) {
                continue;
            }//end if

            $email = $emails[0];

            $witnessAssentsEmail[] = $email->getValue('email');
        }//end foreach

        return $witnessAssentsEmail;

    }//end getHuwelijkWitnessesEmails()


    /**
     * This function creates witnesses from the given data.
     *
     * @param array $huwelijk            The huwelijk array from the request.
     * @param array $witnessAssentsEmail The emails of the witnesses that are already added to the marriage.
     *
     * @return array The witnesses assents array.
     */
    private function createWitnesses(array $huwelijk, array $witnessAssentsEmail): array
    {
        $personSchema = $this->gatewayResourceService->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json', 'common-gateway/huwelijksplanner-bundle');
        $emailSchema  = $this->gatewayResourceService->getSchema('https://klantenBundle.commonground.nu/klant.klantEmail.schema.json', 'common-gateway/huwelijksplanner-bundle');

        $witnessAssents['getuigen'] = [];
        foreach ($huwelijk['getuigen'] as $getuige) {
            if (key_exists('contact', $getuige) === true
                && key_exists('emails', $getuige['contact']) === true
                && is_array($getuige['contact']['emails']) === true
            ) {
                if (in_array($getuige['contact']['emails'][0]['email'], $witnessAssentsEmail) === true) {
                    $this->pluginLogger->error('This witness is already added.');
                    continue;
                }//end if

                $emailObject = new ObjectEntity($emailSchema);
                $emailObject->setValue('email', $getuige['contact']['emails'][0]['email']);
                $emailObject->setValue('naam', $getuige['contact']['emails'][0]['naam']);
                $this->entityManager->persist($emailObject);

                $emailArray   = [];
                $emailArray[] = $emailObject->getId()->toString();
                unset($getuige['contact']['emails']);
                $getuige['contact']['emails'] = $emailArray;
            }//end if

            $person = new ObjectEntity($personSchema);
            $person->hydrate($getuige['contact']);
            $this->entityManager->persist($person);

            // creates an assent and add the person to the partners of this merriage
            $witnessAssents['getuigen'][] = $this->handleAssentService->handleAssent($person, 'witness', $this->data);
        }//end foreach

        return $witnessAssents;

    }//end createWitnesses()


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
        if (!$huwelijkObject = $this->entityManager->getRepository('App:ObjectEntity')->find($id)) {
            $this->pluginLogger->error('Could not find huwelijk with id '.$id);

            return $huwelijkObject->toArray();
        }//end if

        if (isset($huwelijk['getuigen']) === true
            && count($huwelijk['getuigen']) <= 4
        ) {
            if (count($huwelijkObject->getValue('getuigen')) === 4) {
                return $huwelijkObject->toArray();
            }//end if

            // @TODO Check if there are duplicates in the huwelijk getuigen array.
            // Get the emails of the witnesses to validate.
            $witnessAssentsEmail = $this->getHuwelijkWitnessesEmails($huwelijkObject);
            // Create the witnesses
            $witnessAssents = $this->createWitnesses($huwelijk, $witnessAssentsEmail);
            $huwelijkObject->hydrate($witnessAssents);

            $this->entityManager->persist($huwelijkObject);
            $this->entityManager->flush();

            $huwelijkObject = $this->updateChecklistService->checkHuwelijk($huwelijkObject);

            return $huwelijkObject->toArray();
        }//end if

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

        if (in_array('huwelijk', $this->data['parameters']['endpoint']->getPath()) === false) {
            return $this->data;
        }//end if

        if (isset($this->data['parameters']['body']) === false) {
            $this->pluginLogger->error('No data passed');

            return [
                'response' => ['message' => 'No data passed'],
                'httpCode' => 400,
            ];
        }//end if

        if ($this->data['parameters']['method'] !== 'PUT') {
            $this->pluginLogger->error('Not a PUT request');

            return $this->data;
        }//end if

        foreach ($this->data['parameters']['path'] as $path) {
            if (Uuid::isValid($path)) {
                $id = $path;
            }
        }//end foreach

        if (isset($id) === false) {
            return $this->data;
        }//end if

        $huwelijk = $this->inviteWitness($this->data['parameters']['body'], $id);

        $this->data['response'] = $huwelijk;

        return $this->data;

    }//end inviteWitnessHandler()


}//end class
