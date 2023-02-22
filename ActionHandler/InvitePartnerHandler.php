<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\InvitePartnerService;
use Symfony\Component\Security\Core\Security;

class InvitePartnerHandler implements ActionHandlerInterface
{
    private InvitePartnerService $invitePartnerService;
    private Security $security;

    public function __construct(InvitePartnerService $invitePartnerService, Security $security)
    {
        $this->invitePartnerService = $invitePartnerService;
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
            '$id'        => 'https://hp.nl/action/hp.InvitePartnerAction.action.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'InvitePartner',
            'required'   => [],
        ];
    }

    /**
     * This function runs the invitePartnerHandler function.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array|null
     */
    public function run(array $data, array $configuration)
    {
        return $this->invitePartnerService->invitePartnerHandler($data, $configuration, $this->security);
    }
}
