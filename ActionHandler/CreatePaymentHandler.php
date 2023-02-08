<?php

namespace CommonGateway\HuwelijksplannerBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\HuwelijksplannerBundle\Service\CreateAvailabilityService;
use CommonGateway\HuwelijksplannerBundle\Service\PaymentService;

class CreatePaymentHandler implements ActionHandlerInterface
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://huwelijksplanner.nl/schemas/hp.createAvailability.schema.json',
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            'title'       => 'CreateAvailability',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
        ];
    }

    /**
     * This function runs the createAvailability service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array|null
     */
    public function run(array $data, array $configuration): ?array
    {
        return $this->paymentService->createPaymentHandler($data, $configuration);
    }
}
