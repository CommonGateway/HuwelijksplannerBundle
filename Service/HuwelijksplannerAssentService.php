<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use App\Service\ObjectEntityService;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Exception;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This service holds al the logic for the huwelijksplanner plugin.
 */
class HuwelijksplannerAssentService
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;
    private ObjectEntityService $objectEntityService;

    private array $data;
    private array $configuration;

    /**
     * @param ObjectEntityService    $objectEntityService
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        ObjectEntityService $objectEntityService,
        EntityManagerInterface $entityManager
    ) {
        $this->objectEntityService = $objectEntityService;
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
     * Handles Huwelijkslnner actions.
     *
     * @param ObjectEntity $partner
     *
     * @throws Exception
     *
     * @return string|null
     */
    public function mailConsentingPartner(ObjectEntity $partner): ?string
    {
        $person = $partner->getValue('person');
        $phoneNumbers = $person->getValue('telefoonnummers');
        $emailAddresses = $person->getValue('emails');

        if (count($phoneNumbers) > 0 || count($emailAddresses) > 0) {
            // sent email or phoneNumber

            var_dump('hier mail of sms versturen en een secret genereren');
        } else {
            throw new GatewayException('Email or phone number must be present', null, null, ['data' => 'telefoonnummers and/or emails', 'path' => 'Request body', 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        return null;
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param ObjectEntity         $huwelijk
     * @param PersistentCollection $partners
     *
     * @throws Exception
     *
     * @return ObjectEntity|null
     */
    public function huwelijkPartners(ObjectEntity $huwelijk): ?ObjectEntity
    {
        foreach ($huwelijk->getValue('partners') as $partner) {
            var_dump($partner);
            var_dump($partner['requester']);
            $requester = $partner['requester'];
            $person = $partner['person'];
            $subjectIdentificatie = $person['subjectIdentificatie'];
            $klantBsn = $subjectIdentificatie['inpBsn'];

            $partner->setValue('status', $requester === $klantBsn ? 'granted' : 'requested');
            $this->entityManager->persist($partners);

            if ($klantBsn > $requester || $klantBsn < $requester) {
                $this->mailConsentingPartner($partner);
            }
        }

        return $huwelijk;
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws Exception
     *
     * @return array
     */
    public function huwelijksplannerAssentHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if ($this->data['parameters']->getMethod() !== 'PUT') {
            return $this->data;
        }

        var_dump('jojojoojo');
        var_dump($this->data['response']['id']);

        if (!array_key_exists('huwelijksEntityId', $this->configuration)) {
            return $this->data;
        }
        $huwelijkEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['huwelijksEntityId']);

        if (array_key_exists('id', $this->data['response']) &&
            $huwelijk = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $huwelijkEntity, 'id' => $this->data['response']['id']])) {
            if ($partners = $huwelijk->getValue('partners')) {
                var_dump($huwelijk->getValue('partners'));

                $huwelijk = $this->huwelijkPartners($huwelijk);
            }

            $this->entityManager->persist($huwelijk);

            var_dump($this->data['response']['id']);

            var_dump($huwelijk->toArray());
            exit();
        }

        return $this->data['response'];
    }
}
