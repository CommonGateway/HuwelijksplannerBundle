<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\CreateMarriageService;
use Symfony\Component\Security\Core\Security;

class CreateMarriageHandler implements ActionHandlerInterface
{

    /**
     * @var CreateMarriageService
     */
    private CreateMarriageService $service;

    /**
     * @var Security
     */
    private Security $security;


    /**
     * @param CreateMarriageService $service  The CreateMarriageService
     * @param Security              $security The Security
     */
    public function __construct(CreateMarriageService $service, Security $security)
    {
        $this->service  = $service;
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
            '$id'        => 'https://hp.nl/ActionHandler/hp.CreateMarriageHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'CreateMarriage',
            'required'   => [],
            'properties' => [],
        ];

    }//end getConfiguration()


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
        var_dump('createMarriageHandler');

        return $this->service->createMarriageHandler($data, $configuration, $this->security);

    }//end run()


}//end class
