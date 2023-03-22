<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\MollieWebhookService;
use CommonGateway\HuwelijksplannerBundle\Service\PaymentService;

class MollieWebhookHandler implements ActionHandlerInterface
{

    /**
     * @var MollieWebhookService
     */
    private MollieWebhookService $service;


    /**
     * @param MollieWebhookService $service The mollie webhook service
     */
    public function __construct(MollieWebhookService $service)
    {
        $this->service = $service;

    }//end __construct()


    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action schould comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://hp.nl/ActionHandler/hp.CreatePaymentHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'CreatePayment',
            'required'   => [],
            'properties' => [],
        ];

    }//end getConfiguration()


    /**
     * This function runs the createPaymentHandler function.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->service->mollieWebhookHandler($data, $configuration);

    }//end run()


}//end class
