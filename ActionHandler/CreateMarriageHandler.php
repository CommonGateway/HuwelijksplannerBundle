<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\CreateMarriageService;
use Symfony\Component\Security\Core\Security;

class CreateMarriageHandler implements ActionHandlerInterface
{
    private CreateMarriageService $createMarriageService;
    private Security $security;

    public function __construct(CreateMarriageService $createMarriageService, Security $security)
    {
        $this->createMarriageService = $createMarriageService;
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
            'required'   => ['huwelijksEntityId', 'assentEntityId', 'klantEntityId', 'natuurlijkPersoonEntityId'],
            'properties' => [
                'huwelijksEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The id of the huwelijks entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'nullable'    => true,
                    '$ref'        => 'https://commongateway.huwelijksplanner.nl/schemas/hp.huwelijk.schema.json',
                ],
                'assentEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The id of the assent entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'nullable'    => true,
                    '$ref'        => 'https://vng.opencatalogi.nl/schemas/hp.assent.schema.json',
                ],
                'klantEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The id of the klant entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'nullable'    => true,
                    '$ref'        => 'https://klantenBundle.commonground.nu/klant.schema.json',
                ],
                'natuurlijkPersoonEntityId' => [
                    'type'        => 'uuid',
                    'description' => 'The id of the natuurlijk persoon entity',
                    'example'     => 'b484ba0b-0fb7-4007-a303-1ead3ab48846',
                    'nullable'    => true,
                    '$ref'        => 'https://klantenBundle.commonground.nu/natuurlijkPersoon.schema.json',
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
        return $this->createMarriageService->createMarriageHandler($data, $configuration, $this->security);
    }
}
