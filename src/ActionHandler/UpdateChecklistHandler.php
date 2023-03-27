<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\UpdateChecklistService;

class UpdateChecklistHandler implements ActionHandlerInterface
{

    /**
     * @var UpdateChecklistService
     */
    private UpdateChecklistService $service;


    /**
     * @param UpdateChecklistService $service The update checklist Service
     */
    public function __construct(UpdateChecklistService $service)
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
            '$id'        => 'https://hp.nl/ActionHandler/hp.UpdateChecklistHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'UpdateChecklist',
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
        return $this->service->updateChecklistHandler($data, $configuration);

    }//end run()


}//end class
