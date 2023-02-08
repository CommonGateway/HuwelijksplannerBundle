<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

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
     * Handles the assent for the given person
     *
     * @param array|null $huwelijk
     * @param ObjectEntity|null $person
     * @param string|null $id
     * @return ObjectEntity|null
     */
    public function handleAssent(?array $huwelijk, ?ObjectEntity $person, ?string $id): ?ObjectEntity
    {
        if (isset($id) && !$huwelijk = $this->entityManager->getRepository('App:ObjectEntity')->find($id)) {
            return null;
        }

        $phoneNumbers = $person->getValue('telefoonnummers');
        $emailAddresses = $person->getValue('emails');

        if (count($phoneNumbers) > 0 || count($emailAddresses) > 0) {
            // sent email or phoneNumber

            isset($this->io) && $this->io->info('hier mail of sms versturen en een secret genereren');
        } else {
            throw new GatewayException('Email or phone number must be present', null, null, ['data' => 'telefoonnummers and/or emails', 'path' => 'Request body', 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        return null;
    }


//    /**
//     * Handles the assent approval or request.
//     *
//     * @param ?array $data
//     * @param ?array $configuration
//     *
//     * @throws Exception
//     *
//     * @return array
//     */
//    public function handleAssentHandler(?array $data = [], ?array $configuration = []): array
//    {
//        isset($this->io) && $this->io->success('handleAssentHandler triggered');
//
//        $this->data = $data;
//        $this->configuration = $configuration;
//
//        if ($this->data['parameters']->getMethod() !== 'PUT') {
//            return $this->data;
//        }
//
//        if (!array_key_exists('huwelijksEntityId', $this->configuration)) {
//            return $this->data;
//        }
//        $huwelijkEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['huwelijksEntityId']);
//
//        if (
//            array_key_exists('id', $this->data['response']) &&
//            $huwelijk = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $huwelijkEntity, 'id' => $this->data['response']['id']])
//        ) {
//            if ($partners = $huwelijk->getValue('partners')) {
//                $huwelijk = $this->huwelijkPartners($huwelijk);
//            }
//
//            $this->entityManager->persist($huwelijk);
//
//            isset($this->io) && $this->io->info($this->data['response']['id']);
//        }
//
//        return $this->data['response'];
//    }
}
