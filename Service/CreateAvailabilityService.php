<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Response;

/**
 * This service holds al the logic for creating availability.
 */
class CreateAvailabilityService
{
    private SymfonyStyle $io;
    private array $data;
    private array $configuration;

    public function __construct() {
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
     * Creates availability for someone with given date info
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
        isset($this->io) && $this->io->success('createAvailabilityHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        $begin = new DateTime($this->data['parameters']->get('start'));
        $end = new DateTime($this->data['parameters']->get('stop'));

        $interval = new DateInterval($this->data['parameters']->get('interval'));
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
                $resourceArray = $this->data['parameters']->get('resources_could');
            } else {
                $resourceArray = [];
            }

            // end voorbeeld code

            $resultArray[$currentDate->format('Y-m-d')][] = [ // @TODO Add format 'c'
                'start'     => $currentDate->format('Y-m-d\TH:i:sO'), // @TODO Add format 'c'
                'stop'      => $currentDate->add($interval)->format('Y-m-d\TH:i:sO'),
                'resources' => $resourceArray,
            ];
        }

        $this->data['response'] = $resultArray;

        return $this->data;
    }
}
