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
     * @param InviteWitnessService $service  The invite witnes service
     * @param Security             $security The security
     */
    public function __construct(InviteWitnessService $service, Security $security)
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
            '$id'        => 'https://hp.nl/ActionHandler/hp.InviteWitnessHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'InviteWitness',
            'required'   => [],
            'properties' => [
                'mapping' => [
                    'type'        => 'string',
                    'description' => 'The mapping for the assent email and sms data that is made for the partner.
                         The following variables has to be filled in.
                         * `body` is the body of the sms,
                         * `assentName` is the name of the assent that is made for this partner,
                         * `assentDescription` is the description of the assent that is made for this partner,
                         * `url` is the url that the partner is directed to, to confirm the marriage
                         Here you can use the \'requesterName\', \'partnerName\', \'witnessName\', \'moment\', \'location\', \'huwelijk\', \'assentId\' variables to your sentence. 
                         ** The `requesterName` is the name of the partner that requested the marriage. 
                         ** The `partnerName` is the name of the partner that is asked to get married.
                         ** The `witnessName` is the name of the witness that is being invited.
                         ** The `moment` is the moment of the marriage.
                         ** The `location` is the location of the marriage.
                         ** The `huwelijk` is the huwelijks object that is being updated.
                         ** The `assentId` is the assent id that is made for the witness.',
                    'example'     => 'https://huwelijksplanner.nl/mapping/hp.emailAndSmsDataWitness.mapping.json',
                    'reference'   => 'https://huwelijksplanner.nl/mapping/hp.emailAndSmsDataWitness.mapping.json',
                    'required'    => true,
                ],
            ],
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
        return $this->service->inviteWitnessHandler($data, $configuration, $this->security);

    }//end run()


}//end class
