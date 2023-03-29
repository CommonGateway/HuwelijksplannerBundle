<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * This service holds al the logic for creating availability.
 *
 * @author Barry Brands barry@conduction.nl
 *
 * @category Service
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
        $this->pluginLogger = $pluginLogger;
        $this->data = [];
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
    public function createAvailabilityHandler(?array $data = [], ?array $configuration = []): array
    {
        $this->pluginLogger->debug('createAvailabilityHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        if (isset($this->data['parameters']['query']['start']) === false
            || isset($this->data['parameters']['query']['stop']) === false
            || isset($this->data['parameters']['query']['interval']) === false
            || isset($this->data['parameters']['query']['resources_could']) === false
        ) {
            return [
                'response'     => ['message' => 'Add a start, stop (both datetime), interval (dateinterval) and resources_could[] (product id\'s) to your query paramterse on this endpoint.'],
                'responseCode' => 400,
            ];
        }//end if

        $begin = new DateTime($this->data['query']['start']);
        $end = new DateTime($this->data['query']['stop']);

        $interval = new DateInterval($this->data['query']['interval']);
        $period = new DatePeriod($begin, $interval, $end);

        $resultArray = [];
        foreach ($period as $currentDate) {
            // start voorbeeld code
            $dayStart = clone $currentDate;
            $dayStop = clone $currentDate;

            $dayStart->setTime(9, 0);
            $dayStop->setTime(17, 0);

            // @TODO Add format 'c'
            if ($currentDate->format('Y-m-d H:i:s') >= $dayStart->format('Y-m-d H:i:s') && $currentDate->format('Y-m-d H:i:s') < $dayStop->format('Y-m-d H:i:s')) {
                $resourceArray = $this->data['query']['resources_could'];
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

        $this->data['response'] = new Response(json_encode($resultArray), 200);

        return $this->data;
    }//end createAvailabilityHandler()
}//end class
