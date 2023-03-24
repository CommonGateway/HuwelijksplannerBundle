<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service holds al the logic for creating availability.
 */
class CreateAvailabilityService
{

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
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;


    /**
     * @param LoggerInterface $pluginLogger The Logger Interface
     */
    public function __construct(
        LoggerInterface $pluginLogger,
        EntityManagerInterface $entityManager
    ) {
        $this->pluginLogger  = $pluginLogger;
        $this->entityManager = $entityManager;
        $this->data          = [];
        $this->configuration = [];

    }//end __construct()


    /**
     * Creates availability for someone with given date info.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @throws Exception
     *
     * @return array
     */
    public function createAvailabilityHandler(?array $data=[], ?array $configuration=[]): array
    {
        $this->pluginLogger->debug('createAvailabilityHandler triggered');
        $this->data          = $data;
        $this->configuration = $configuration;

        $begin = new DateTime($this->data['parameters']->get('start'));
        $end   = new DateTime($this->data['parameters']->get('stop'));

        $givenBeginStamp = $begin->getTimestamp();
        $givenEndStamp   = $end->getTimestamp();

        $interval = new DateInterval($this->data['parameters']->get('interval'));
        $period   = new DatePeriod($begin, $interval, $end);

        // @TODO get products from params
        $ceremonie = $this->data['parameters']->get('?');
        // @TODO end
        // @TODO get availability for products
        $availabilityEntity = $this->entityManager->getRepository('App:Entity')->findBy(['reference' => 'https://huwelijksplanner.nl/schemas/hp.availability.schema.json']);
        $resourceAttribute  = $this->entityManager->getRepository('App:Attribute')->findBy(['name' => 'resource', 'entity' => $availabilityEntity]);
        // @TODO end
        // @TODO Repeat for all products
        $resourceValues         = $this->entityManager->getRepository('App:Value')->findBy(['attribute' => $resourceAttribute, 'stringValue' => $ceremonie]);
        $ceremonieAvailabilties = [];
        foreach ($resourceValues as $value) {
            $ceremonieAvailabilties = $value->getObject()->toArray();
        }

        foreach ($ceremonieAvailabilties as $availability) {
            $start      = new DateTime($availability['startDate']);
            $startStamp = $start->getTimestamp();
            $end        = new DateTime($availability['endDate']);
            $endStamp   = $end->getTimestamp();

            if (( $givenBeginStamp > $startStamp && $givenBeginStamp < $endStamp ) || ( $endStamp > $startStamp && $endStamp < $endStamp )) {
                return ['response' => ['message' => 'Ceremonie not available on given date']];
            }
        }

        // @TODO end
        // @TODO this code creates a availability?
        $resultArray = [];
        foreach ($period as $currentDate) {
            // start voorbeeld code
            $dayStart = clone $currentDate;
            $dayStop  = clone $currentDate;

            $dayStart->setTime(9, 0);
            $dayStop->setTime(17, 0);

            $formattedCurrentDate = $currentDate->format('Y-m-d\TH:i:sO');
            $formattedDayStart    = $dayStart->format('Y-m-d\TH:i:sO');
            $formattedDayStop     = $dayStop->format('Y-m-d\TH:i:sO');

            $currentDateStamp = $currentDate->getTimestamp();
            $dayStartStamp    = $dayStart->getTimestamp();
            $dayStopStamp     = $dayStart->getTimestamp();

            if ($currentDateStamp >= $dayStartStamp && $currentDateStamp < $dayStopStamp) {
                $resourceArray = $this->data['parameters']->get('resources_could');
            } else {
                $resourceArray = [];
            }

            // end voorbeeld code
            $resultArray[$currentDate->format('Y-m-d')][] = [
                'start'     => $currentDate->format('Y-m-d\TH:i:sO'),
                'stop'      => $currentDate->add($interval)->format('Y-m-d\TH:i:sO'),
                'resources' => $resourceArray,
            ];
        }//end foreach
        // @TODO end
        $this->data['response'] = $resultArray;

        return $this->data;

    }//end createAvailabilityHandler()


}//end class
