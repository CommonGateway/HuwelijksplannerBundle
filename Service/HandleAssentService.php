<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Response;

/**
 * This service holds al the logic for approving or requesting a assent.
 */
class HandleAssentService
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;

    private array $data;
    private array $configuration;
    private ?Schema $assentSchema;

    /**
     * @param EntityManagerInterface $entityManager
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
     * @param SymfonyStyle $io
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
    }

    /**
     * Get the assent schema.
     *
     * @return bool
     */
    private function getAssentSchema()
    {
        if (!$this->assentSchema = $this->entityManager->getRepository(Schema::class)->findOneBy(['reference' => 'https://huwelijksplanner.nl/schemas/hp.assent.schema.json'])) {
            isset($this->io) && $this->io->error('No schema found for https://huwelijksplanner.nl/schemas/hp.assent.schema.json');

            throw new Exception('No schema found for https://huwelijksplanner.nl/schemas/hp.assent.schema.json');

            return null;
        }

        return $this->assentSchema;
    }

    /**
     * Handles the assent for the given person and sends an email or sms
     *
     * @param array|null $huwelijk
     * @param ObjectEntity|null $person
     * @param string|null $id
     * @return ObjectEntity|null
     */
    public function handleAssent(ObjectEntity $person): ?ObjectEntity
    {
        if (!$assentSchema = $this->getAssentSchema()) {
            isset($this->io) && $this->io->error('No AssentSchema found when trying create an assent');

            return null;
        }

        $assent = new ObjectEntity($assentSchema);
        $assent->hydrate([
            'name' => $person->getValue('voornaam'),
            'description' => null,
            'request' => null,
            'forwardUrl' => null,
            'property' => null,
            'process' => null,
            'contact' => $person,
            'status' => 'requested',
            'requester' => null, // the bsn of the person
            'revocable' => true
        ]);
        $this->entityManager->persist($assent);

        $phoneNumbers = $person->getValue('telefoonnummers');
        $emailAddresses = $person->getValue('emails');

        if (count($phoneNumbers) <= 0 || count($emailAddresses) <= 0) {
            throw new GatewayException('Email or phone number must be present', null, null, ['data' => 'telefoonnummers and/or emails', 'path' => 'Request body', 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        isset($this->io) && $this->io->info('hier mail of sms versturen en een secret genereren');

        foreach ($emailAddresses as $emailAddress) {

        }

        foreach ($phoneNumbers as $phoneNumber) {

        }

        return $assent;
    }
}
