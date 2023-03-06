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
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'CreateMarriage',
            'required'   => ['huwelijksEntityId', 'assentEntityId', 'klantEntityId', 'natuurlijkPersoonEntityId'],
        ];
    }

    /**
     * This function runs the createMarriage function.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array|null
     */
    public function run(array $data, array $configuration)
    {
        return $this->createMarriageService->createMarriageHandler($data, $configuration, $this->security);
    }
}
