<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\InviteWitnessService;
use Symfony\Component\Security\Core\Security;

class InviteWitnessHandler implements ActionHandlerInterface
{

    /**
     * @var InviteWitnessService
     */
    private InviteWitnessService $service;

    /**
     * @var Security
     */
    private Security $security;


    /**
     * @param InviteWitnessService $service The invite witnes service
     * @param Security $security The security
     */
    public function __construct(InviteWitnessService $service, Security $security)
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
            '$id'        => 'https://hp.nl/ActionHandler/hp.InviteWitnessHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'InviteWitness',
            'required'   => [],
            'properties' => []
        ];

    }//end getConfiguration()


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
        var_dump('inviteWitnessHandler');

        return $this->service->inviteWitnessHandler($data, $configuration, $this->security);

    }//end run()


}//end class
