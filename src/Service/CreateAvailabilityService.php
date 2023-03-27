<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;

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
     * @param LoggerInterface $pluginLogger The Logger Interface
     */
    public function __construct(
        LoggerInterface $pluginLogger
    ) {
        $this->pluginLogger  = $pluginLogger;
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

        $begin = new DateTime($this->data['parameters']['query']['start']);
        $end   = new DateTime($this->data['parameters']['query']['stop']);

        $interval = new DateInterval($this->data['parameters']['query']['interval']);
        $period   = new DatePeriod($begin, $interval, $end);

        $resultArray = [];
        foreach ($period as $currentDate) {
            // start voorbeeld code
            $dayStart = clone $currentDate;
            $dayStop  = clone $currentDate;

            $dayStart->setTime(9, 0);
            $dayStop->setTime(17, 0);

            // @TODO Add format 'c'
            if ($currentDate->format('Y-m-d H:i:s') >= $dayStart->format('Y-m-d H:i:s') && $currentDate->format('Y-m-d H:i:s') < $dayStop->format('Y-m-d H:i:s')) {
                $resourceArray = $this->data['parameters']['query']['resources_could'];
            } else {
                $resourceArray = [];
            }

            // end voorbeeld code
            $resultArray[$currentDate->format('Y-m-d')][] = [
            // @TODO Add format 'c'
                'start'     => $currentDate->format('Y-m-d\TH:i:sO'),
            // @TODO Add format 'c'
                'stop'      => $currentDate->add($interval)->format('Y-m-d\TH:i:sO'),
                'resources' => $resourceArray,
            ];
        }//end foreach

        $this->data['response'] = $resultArray;

        return $this->data;

    }//end createAvailabilityHandler()


}//end class
