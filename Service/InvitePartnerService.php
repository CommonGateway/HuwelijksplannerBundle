<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

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
     * @var SymfonyStyle
     */
    private SymfonyStyle $symfonyStyle;

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
    private LoggerInterface $logger;

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
     * @param HandleAssentService    $handleAssentService    The Handle Assent Service
     * @param UpdateChecklistService $updateChecklistService The Update Checklist Service
     * @param Security               $security               The Security
     * @param LoggerInterface        $logger                 The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        HandleAssentService $handleAssentService,
        UpdateChecklistService $updateChecklistService,
        Security $security,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->data = [];
        $this->configuration = [];
        $this->handleAssentService = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security = $security;
        $this->logger = $logger;
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
     * This function validates and creates the huwelijk object
     * and creates an assent for the current user.
     */
    private function invitePartner(array $huwelijk, ?string $id): ?array
    {
        if (!$huwelijkObject = $this->entityManager->getRepository('App:ObjectEntity')->find($id)) {
            isset($this->io) && $this->io->error('Could not find huwelijk with id '.$id); // @TODO throw exception ?
            $this->logger->error('Could not find huwelijk with id '.$id);

            $this->data['response'] = 'Could not find huwelijk with id '.$id;

            return $this->data;
        }
        
        // @TODO check if the requester has already a partner
        // if so throw error else continue

        if (isset($huwelijk['partners']) === true
            && count($huwelijk['partners']) === 1
        ) {
            if (count($huwelijkObject->getValue('partners')) < 2) {
                // @TODO update partner?

                return $huwelijkObject->toArray();
            }
            $personSchema = $this->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json');

            foreach ($huwelijkObject->getValue('partners') as $partner) {
                if (empty($partner->getValue('requester')) === false) {
                    continue;
                }

                if (empty($person = $partner->getValue('contact')) === true) {
                    $person = new ObjectEntity($personSchema);
                }

                $person->hydrate($huwelijk['partners'][0]['contact']);
                $this->entityManager->persist($person);

                $partner->setValue('contact', $person);
                $this->entityManager->persist($partner);

                $this->entityManager->flush();
            }

            $huwelijkObject = $this->updateChecklistService->checkHuwelijk($huwelijkObject);

            $this->entityManager->persist($huwelijkObject);
            $this->entityManager->flush();
        }

        return $huwelijkObject->toArray();
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
    public function invitePartnerHandler(?array $data = [], ?array $configuration = []): ?array
    {
        isset($this->io) && $this->io->success('invitePartnerHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        if (in_array('huwelijk', $this->data['parameters']['endpoint']->getPath()) === false) {
            return $this->data;
        }

        if (!isset($this->data['parameters']['body'])) {
            isset($this->io) && $this->io->error('No data passed'); // @TODO throw exception ?
            $this->logger->error('No data passed');

            return ['response' => ['message' => 'No data passed'], 'httpCode' => 400];
        }

        if ($this->data['parameters']['method'] !== 'PATCH') {
            isset($this->io) && $this->io->error('Not a PATCH request');
            $this->logger->error('Not a PATCH request');

            return $this->data;
        }

        foreach ($this->data['parameters']['path'] as $path) {
            if (Uuid::isValid($path)) {
                $id = $path;
            }
        }

        if (!isset($id)) {
            return $this->data;
        }

        $huwelijk = $this->invitePartner($this->data['parameters']['body'], $id);

        $this->data['response'] = $huwelijk;

        return $this->data;
    }//end invitePartnerHandler()
}
