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
            '$id'        => 'https://hp.nl/ActionHandler/hp.InvitePartnerHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'InvitePartner',
            'required'   => [],
            'properties' => [
                'mapping' => [
                    'type'        => 'string',
                    'description' => 'The mapping for the assent email and sms data that is made for the partner.
                         The following variables has to be filled in.
                         * `header` is the header of the email,
                         * `salutation` is the salutation of the email,
                         * `bodyEmail` is the body of the email,
                         * `bodyMessage` is the body of the sms,
                         * `assentName` is the name of the assent that is made for this partner,
                         * `assentDescription` is the description of the assent that is made for this partner,
                         * `url` is the url that the partner is directed to, to confirm the marriage
                         Here you can use the \'requesterName\', \'partnerName\', \'moment\', \'location\', \'huwelijk\', \'assentId\' variables to your sentence. 
                         ** The requesterName is the name of the partner that requested the marriage. 
                         ** The partnerName is the name of the partner that is asked to get married.
                         ** The `moment` is the moment of the marriage.
                         ** The `location` is the location of the marriage.
                         ** The `huwelijk` is the huwelijks object that is being updated.
                         ** The `assentId` is the assent id that is made for the partner.',
                    'example'     => 'https://huwelijksplanner.nl/mapping/hp.emailAndSmsDataPartner.mapping.json',
                    'reference'   => 'https://huwelijksplanner.nl/mapping/hp.emailAndSmsDataPartner.mapping.json',
                    'required'    => true,
                ],
            ],
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
        return $this->service->invitePartnerHandler($data, $configuration, $this->security);

    }//end run()


}//end class
