<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\UpdateChecklistService;

class UpdateChecklistHandler implements ActionHandlerInterface
{
    /**
     * @var UpdateChecklistService
     */
    private UpdateChecklistService $updateChecklistService;

    /**
     * @param UpdateChecklistService $updateChecklistService The update checklist Service
     */
    public function __construct(UpdateChecklistService $updateChecklistService)
    {
        $this->updateChecklistService = $updateChecklistService;

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
            'properties' => []
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
        return $this->updateChecklistService->updateChecklistHandler($data, $configuration);

    }//end run()

    
}//end class
