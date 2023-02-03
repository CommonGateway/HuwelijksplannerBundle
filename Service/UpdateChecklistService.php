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
 * This service holds al the logic for checksing data from the marriage request and updating the associated checklist.
 */
class UpdateChecklistService
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private SymfonyStyle $io;
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

    public function checkHuwelijk(ObjectEntity $huwelijk): ObjectEntity
    {
        $checklist = [];

        // Check partners
        if (count($huwelijk->getValueByAttribute('partners')) < 2) {
            $checklist['partners'] = 'Voor een huwelijk/partnerschap zijn minimaal 2 partners nodig';
        } elseif (count($huwelijk->getValueByAttribute('partners')) > 2) {
            $checklist['partners'] = 'Voor een huwelijk/partnerschap kunnen maximaal 2 partners worden opgegeven';
        }
        // Check getuigen
        // @todo eigenlijk is het minimaal 1 en maximaal 2 getuigen per partner
        if (count($huwelijk->getValueByAttribute('getuigen')) < 2) {
            $checklist['getuigen'] = 'Voor een huwelijk/partnerschap zijn minimaal 2 getuigen nodig';
        } elseif (count($huwelijk->getValueByAttribute('getuigen')) > 4) {
            $checklist['getuigen'] = 'Voor een huwelijk/partnerschap kunnen maximaal 4 getuigen worden opgegeven';
        }
        // Kijken naar locatie
        if (!$huwelijk->getValueByAttribute('locatie')) {
            $checklist['locatie'] = 'Nog geen locatie opgegeven';
        }
        // Kijken naar ambtenaar
        if (!$huwelijk->getValueByAttribute('ambtenaar')) {
            $checklist['ambtenaar'] = 'Nog geen ambtenaar opgegeven';
        }
        // @todo trouwdatum minimaal 2 weken groter dan aanvraag datum

        $huwelijk->setValue('checklist', $checklist);

        $this->objectEntityService->saveObject($huwelijk);

        return $huwelijk;
    }

    /**
     * Checks data from the marriage request and updates the associated checklist.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     * @return array
     */
    public function updateChecklistHandler(?array $data = [], ?array $configuration = []): array
    {
        isset($this->io) && $this->io->success('updateChecklistHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        // Check if the incommming data exisits and is a huwelijk object
        if (
            in_array('id', $this->data) &&
            $huwelijk = $this->objectEntityService->getObject(null, $this->data['id']) &&
            $huwelijk->getEntity()->getName() == 'huwelijk'
        ) {
            return $this->checkHuwelijk($huwelijk)->toArray();
        }

        return $data;
    }
}
