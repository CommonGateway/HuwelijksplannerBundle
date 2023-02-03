<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\HuwelijksplannerService;

class HuwelijksplannerCalendarHandler implements ActionHandlerInterface
{
    private HuwelijksplannerService $huwelijksplannerService;

    public function __construct(HuwelijksplannerService $huwelijksplannerService)
    {
        $this->huwelijksplannerService = $huwelijksplannerService;
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
        return $this->huwelijksplannerService->huwelijksplannerCalendarHandler($data, $configuration);
    }
}
