<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\InviteWitnessService;
use Symfony\Component\Security\Core\Security;

class InviteWitnessHandler implements ActionHandlerInterface
{
    private InviteWitnessService $inviteWitnessService;
    private Security $security;

    public function __construct(InviteWitnessService $inviteWitnessService, Security $security)
    {
        $this->inviteWitnessService = $inviteWitnessService;
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
            '$id'        => 'https://hp.nl/action/hp.InviteWitnessAction.action.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'InviteWitness',
            'required'   => [],
        ];
    }

    /**
     * This function runs the inviteWitnessHandler function.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array|null
     */
    public function run(array $data, array $configuration)
    {
        return $this->inviteWitnessService->inviteWitnessHandler($data, $configuration, $this->security);
    }
}
