<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\HandleAssentService;

class HandleAssentHandler implements ActionHandlerInterface
{
    private HandleAssentService $handleAssentService;

    public function __construct(HandleAssentService $handleAssentService)
    {
        $this->handleAssentService = $handleAssentService;
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
            'title'      => 'HandleAssent',
            'required'   => ['huwelijksEntityId'],
        ];
    }

    /**
     * This function runs the handleAssent function.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->handleAssentService->handleAssentHandler($data, $configuration);
    }
}
