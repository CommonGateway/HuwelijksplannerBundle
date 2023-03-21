<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\InvitePartnerService;
use Symfony\Component\Security\Core\Security;

class InvitePartnerHandler implements ActionHandlerInterface
{
    /**
     * @var InvitePartnerService
     */
    private InvitePartnerService $service;

    /**
     * @var Security
     */
    private Security $security;

    /**
     * @param InvitePartnerService $service  The invite partner service
     * @param Security             $security THe security
     */
    public function __construct(InvitePartnerService $service, Security $security)
    {
        $this->service = $service;
        $this->security = $security;
    }//end __construct()

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action schould comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://hp.nl/ActionHandler/hp.InvitePartnerHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'InvitePartner',
            'required'   => [],
            'properties' => [],
        ];
    }//end getConfiguration()

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
        var_dump('invitePartnerHandler');

        return $this->service->invitePartnerHandler($data, $configuration, $this->security);
    }//end run()
}//end class
