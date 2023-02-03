<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\HuwelijksplannerCalendarService;

class HuwelijksplannerCalendarHandler implements ActionHandlerInterface
{
    private HuwelijksplannerCalendarService $huwelijksplannerCalendarService;

    public function __construct(HuwelijksplannerCalendarService $huwelijksplannerCalendarService)
    {
        $this->huwelijksplannerCalendarService = $huwelijksplannerCalendarService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://vng.opencatalogi.nl/schemas/hp.availabilityCheck.schema.json',
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            'title'       => 'Huwelijksplanner calendar Action',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [],
        ];
    }

    /**
     * This function runs the service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->huwelijksplannerCalendarService->huwelijksplannerCalendarHandler($data, $configuration);
    }
}
