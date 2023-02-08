<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\UpdateChecklistService;

class UpdateChecklistHandler implements ActionHandlerInterface
{
    private UpdateChecklistService $updateChecklistService;

    public function __construct(UpdateChecklistService $updateChecklistService)
    {
        $this->updateChecklistService = $updateChecklistService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action schould comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://vng.opencatalogi.nl/schemas/hp.assent.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'UpdateChecklist',
            'required'   => ['huwelijksEntityId'],
        ];
    }

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
    }
}
