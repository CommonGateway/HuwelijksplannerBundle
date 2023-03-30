<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
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
        $this->entityManager = $entityManager;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->data = [];
        $this->configuration = [];
        $this->handleAssentService = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security = $security;
        $this->pluginLogger = $pluginLogger;
    }//end __construct()

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
        if (!$huwelijkObject = $this->entityManager->getRepository('App:ObjectEntity')->find($id)) {
            $this->pluginLogger->error('Could not find huwelijk with id '.$id);

            $this->data['response'] = 'Could not find huwelijk with id '.$id;

            return $this->data;
        }//end if

        // @TODO check if the requester has already a partner
        // if so throw error else continue
        if (isset($huwelijk['partners']) === true
            && count($huwelijk['partners']) === 1
        ) {
            if (count($huwelijkObject->getValue('partners')) > 1) {
                // @TODO update partner?
                return $huwelijkObject->toArray();
            }//end if

            if (count($huwelijkObject->getValue('partners')) !== 1) {
                $this->pluginLogger->error('You cannot add a partner before the requester is set.');

                return $huwelijkObject;
            }//end if

            $personSchema = $this->gatewayResourceService->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json', 'common-gateway/huwelijksplanner-bundle');

            $person = new ObjectEntity($personSchema);
            $person->hydrate($huwelijk['partners'][0]['contact']);
            $this->entityManager->persist($person);
            $this->entityManager->flush();

            $partners = $huwelijkObject->getValue('partners');
            $requesterAssent['partners'][] = $partners[0]->getId()->toString();
            $requesterAssent['partners'][] = $this->handleAssentService->handleAssent($person, 'partner', $this->data)->getId()->toString();
            $huwelijkObject->hydrate($requesterAssent);

            $this->entityManager->persist($huwelijkObject);
            $this->entityManager->flush();

            $huwelijkObject = $this->updateChecklistService->checkHuwelijk($huwelijkObject);
        }//end if

        return $huwelijkObject->toArray();
    }//end invitePartner()

    /**
     * Creates the marriage request object.
     *
     * @param ?array $data          The data array.
     * @param ?array $configuration The configuration array.
     *
     * @throws Exception
     *
     * @return array The data array
     */
    public function invitePartnerHandler(?array $data = [], ?array $configuration = []): array
    {
        $this->pluginLogger->debug('invitePartnerHandler triggered');
        $this->data = $data;
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

        if (isset($this->data['response']['_self']['id']) === false) {
            return $this->data;
        }//end if

        $huwelijk = $this->invitePartner($this->data['parameters']['body'], $this->data['response']['_self']['id']);

        $this->data['response'] = $huwelijk;

        return $this->data;
    }//end invitePartnerHandler()
}//end class
