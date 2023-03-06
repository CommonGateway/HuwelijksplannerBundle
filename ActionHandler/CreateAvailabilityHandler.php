<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\CreateAvailabilityService;

class CreateAvailabilityHandler implements ActionHandlerInterface
{
    private CreateAvailabilityService $createAvailabilityService;

    public function __construct(CreateAvailabilityService $createAvailabilityService)
    {
        $this->createAvailabilityService = $createAvailabilityService;
    }

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://huwelijksplanner.nl/schemas/hp.createAvailability.schema.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'CreateAvailability',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
        ];
    }

    /**
     * This function runs the createAvailability service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->createAvailabilityService->createAvailabilityHandler($data, $configuration);
    }
}
