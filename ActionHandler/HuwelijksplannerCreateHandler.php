<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\HuwelijksplannerService;
use Symfony\Component\Security\Core\Security;

class HuwelijksplannerCreateHandler implements ActionHandlerInterface
{
    private HuwelijksplannerService $huwelijksplannerService;
    private Security $security;

    public function __construct(HuwelijksplannerService $huwelijksplannerService, Security $security)
    {
        $this->huwelijksplannerService = $huwelijksplannerService;
        $this->security = $security;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action schould comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://vng.opencatalogi.nl/schemas/hp.huwelijk.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'Huwelijksplanner create Action',
            'required'   => ['huwelijksEntityId'],
            'properties' => [
                'huwelijksEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The id of the huwelijks entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'nullable'    => true,
                    '$ref'        => 'https://commongateway.huwelijksplanner.nl/schemas/hp.huwelijk.schema.json',
                ],
            ],
        ];
    }

    /**
     * This function runs the zaak type plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->huwelijksplannerService->huwelijksplannerCreateHandler($data, $configuration, $this->security);
    }
}
