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
class HuwelijksplannerCreateService
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

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @throws Exception
     *
     * @return array
     */
    public function huwelijksplannerCreateHandler(?array $data = [], ?array $configuration = [], Security $security): array
    {
        isset($this->io) && $this->io->success('huwelijksplannerCreateHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;
        var_dump('hihihi');

        if ($this->data['parameters']->getMethod() !== 'POST') {
            return $this->data;
        }

        if (!array_key_exists('huwelijksEntityId', $this->configuration)) {
            return $this->data;
        }
        $huwelijkEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['huwelijksEntityId']);

        if (array_key_exists('id', $this->data['response']) &&
            $huwelijk = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $huwelijkEntity, 'id' => $this->data['response']['id']])) {
            $requestPartnerAssent = [
                'name'        => $security->getUser()->getUserName(),
                'description' => null,
                'property'    => null,
                'contact'     => null,
                'person'      => 'natuurlijk_persoon',
                'status'      => null,
                'requester'   => null,
            ];
            var_dump('hihihi');
            var_dump($security->getUser()->getUserName());

            var_dump($huwelijk->toArray());
            exit();
        }

        return $this->data['response'];
    }
}
