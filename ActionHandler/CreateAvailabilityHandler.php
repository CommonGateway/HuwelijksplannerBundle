<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\CreateAvailabilityService;

class CreateAvailabilityHandler implements ActionHandlerInterface
{
    /**
     * @var CreateAvailabilityService
     */
    private CreateAvailabilityService $service;

    /**
     * @param CreateAvailabilityService $service The CreateAvailabilityService
     */
    public function __construct(CreateAvailabilityService $service)
    {
        $this->service = $service;
    }//end __construct()

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://hp.nl/ActionHandler/hp.CreateAvailabilityHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'CreateAvailability',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [],
        ];
    }//end getConfiguration()

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
        return $this->service->createAvailabilityHandler($data, $configuration);
    }//end run()
}//end class
