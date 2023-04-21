<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\UpdateChecklistService;
use CommonGateway\HuwelijksplannerBundle\Service\UpdateMarriageService;

class UpdateMarriageHandler implements ActionHandlerInterface
{

    /**
     * @var UpdateMarriageService
     */
    private UpdateMarriageService $service;


    /**
     * @param UpdateMarriageService $service The update marriage Service
     */
    public function __construct(UpdateMarriageService $service)
    {
        $this->service = $service;

    }//end __construct()


    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action schould comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://hp.nl/ActionHandler/hp.UpdateMarriageHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'UpdateMarriage',
            'required'   => [],
            'properties' => [],
        ];

    }//end getConfiguration()


    /**
     * This function runs the updateCheckList function.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->service->updateMarriageHandler($data, $configuration);

    }//end run()


}//end class
