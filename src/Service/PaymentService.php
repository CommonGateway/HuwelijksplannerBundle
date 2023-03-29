<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Money\Currency;
use Money\Money;

/**
 * This service holds al the logic for mollie payments.
 */
class PaymentService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CallService
     */
    private CallService $callService;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $gatewayResourceService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var array
     */
    private array $configuration;


    /**
     * @param EntityManagerInterface $entityManager          The Entity Manager Interface.
     * @param CallService            $callService            The Call Service.
     * @param SynchronizationService $syncService            The Synchronization Service.
     * @param GatewayResourceService $gatewayResourceService The Gateway Resource Service.
     * @param LoggerInterface        $pluginLogger           The Logger Interface.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CallService $callService,
        SynchronizationService $syncService,
        GatewayResourceService $gatewayResourceService,
        LoggerInterface $pluginLogger
    ) {
        $this->entityManager          = $entityManager;
        $this->callService            = $callService;
        $this->syncService            = $syncService;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->pluginLogger           = $pluginLogger;

        $this->data          = [];
        $this->configuration = [];

    }//end __construct()


    /**
     * Check the auth of the given source.
     *
     * @param Source $source The given source to check the api key.
     *
     * @return bool If the api key is set or not.
     */
    public function checkSourceAuth(Source $source): bool
    {
        if ($source->getApiKey() === null) {
            $this->pluginLogger->error('No auth set for Source: '.$source->getName().'.', ['plugin' => 'common-gateway/huwelijksplanner-bundle']);

            return false;
        }//end if

        return true;

    }//end checkSourceAuth()


    /**
     * Get price from a single product.
     * 
     * @param  string      $productId
     * 
     * @return array       Product object.
     */
    private function getProductObject(string $productId): array
    {
        $productObject = $this->entityManager->getRepository('App:ObjectEntity')->find($productId);

        return $productObject->toArray() ?? null;

    }//end getProductObject()

    /**
     * Get price from a single product.
     * 
     * @param  array       $product
     * 
     * @return string|null Price.
     */
    private function getProductPrice(array $product)
    {
        if (isset($product['vertalingen'][0]['kosten'])) {
            return $product['vertalingen'][0]['kosten'];
        }//end if

        return null;

    }//end getProductPrice()


    /**
     * Get product prices from this marriage.
     *
     * @param array $huwelijk
     *
     * @return array $productPrices
     */
    public function getProductPrices(array $huwelijk): array
    {
        $productPrices = [];
        foreach ($huwelijk as $key => $value) {
            if (in_array($key, ['type', 'ceremonie', 'locatie', 'ambtenaar', 'producten'])) {
                if ($key === 'producten') {
                    foreach ($value as $extraProduct) {
                        // @todo move this to validation
                        if ($extraProduct !== null) {
                            if (is_array($extraProduct) === false) {
                                $extraProduct = $this->getProductObject($extraProduct);
                            }//end if
                            if ($extraProduct !== null) {
                                $productPrices[] = $this->getProductPrice($extraProduct);
                            }//end if
                        }//end if
                    }//end foreach

                    continue;
                }//end if

                // @todo move this to validation
                if ($value !== null) {
                    if (is_array($value) === false) {
                        $productObject = $this->entityManager->getRepository('App:ObjectEntity')->find($value);
                    }//end if
                    if ($productObject !== null) {
                        $productPrices[] = $this->getProductPrice($productObject);
                    }//end if
                }//end if
            }//end if
        }//end foreach

        return $productPrices;

    }//end getProductPrices()


    /**
     * Calculates total price with given prices and currency.
     *
     * @param array       prices.
     * @param string|null ISO 4271 currency.
     *
     * @return string total price.
     */
    public function calculatePrice(array $prices, ?string $currency='EUR'): string
    {
        $currency   = new Currency($currency);
        $totalPrice = new Money(0, $currency);

        foreach ($prices as $price) {
            $price      = str_replace('EUR ', '', $price);
            $totalPrice = $totalPrice->add(new Money($price, $currency));
        }

        return $totalPrice->getAmount();

    }//end calculatePrice()


    /**
     * Creates a payment object.
     * The required fields in the paymentArray are:
     * The amount object with currency and value.
     * The string descrtiption.
     * The string redirectUrl were mollie has to redirect to after the payment.
     * The method array with the payment methods.
     *
     * @param array $paymentArray The body for the payment request.
     *
     * @return array|null
     */
    public function createMolliePayment(array $paymentArray): ?array
    {
        $mollieEntity = $this->gatewayResourceService->getSchema('https://huwelijksplanner.nl/schemas/hp.mollie.schema.json', 'common-gateway/huwelijksplanner-bundle');
        $source       = $this->gatewayResourceService->getSource('https://huwelijksplanner.nl/source/hp.mollie.source.json', 'common-gateway/huwelijksplanner-bundle');
        if ($this->checkSourceAuth($source) === false) {
            return [
                'message' => 'No authorization set for the mollie source.',
                'status'  => 400,
            ];
        }//end if

        $queryConfig = ['body' => \Safe\json_encode($paymentArray)];

        try {
            $response = $this->callService->call($source, '/v2/payments', 'POST', $queryConfig);
            $payment  = json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $exception) {
            $this->pluginLogger->error('Could not post a payment with source: '.$source->getName());
        }

        if (empty($payment) === true) {
            $this->pluginLogger->error('Could not post a payment with source: '.$source->getName());

            return [
                'message' => 'Could not post a payment with source: '.$source->getName(),
                'status'  => 400,
            ];
        }//end if

        $this->pluginLogger->debug('The message was sent successfully');

        $synchronization = $this->syncService->findSyncBySource($source, $mollieEntity, $payment['id']);
        $this->pluginLogger->debug('Sync with id: '.$synchronization->getId()->toString());

        $synchronization = $this->syncService->synchronize($synchronization, $payment);

        return $synchronization->getObject()->toArray();

    }//end createMolliePayment()


    /**
     * Creates a payment object.
     *
     * @return array|null
     */
    public function createPayment(): ?array
    {
        // @TODO add the values amount from huwelijk object etc to array
        $paymentSchema = $this->gatewayResourceService->getSchema('https://huwelijksplanner.nl/schemas/hp.mollie.schema.json', 'common-gateway/huwelijksplanner-bundle');

        $huwelijkId = $this->data['query']['huwelijk'];

        if ($huwelijkId === null) {
            throw new BadRequestHttpException('No huwelijk id given in the parameter huwelijk.');
        }//end if

        $huwelijkObject = $this->entityManager->find('App:ObjectEntity', $huwelijkId);
        if ($huwelijkObject instanceof ObjectEntity === false) {
            throw new BadRequestHttpException('Cannot find huwelijk with given id: '.$huwelijkId);
        }//end if

        // Get all prices from the products
        $productPrices = $this->getProductPrices($huwelijkObject->toArray());
        // Calculate new price
        $kosten = 'EUR '.$this->calculatePrice($productPrices, 'EUR');

        $explodedAmount = explode(' ', $kosten);

        $paymentArray = [
            'amount'      => [
                'currency' => $explodedAmount[0],
                'value'    => $explodedAmount[1],
            ],
            'description' => 'Payment made for huwelijk with id: '.$huwelijkId,
            'redirectUrl' => $this->configuration['redirectUrl'],
            'webhookUrl'  => $this->configuration['webhookUrl'],
            'method'      => $this->configuration['method'],
        ];

        return $this->createMolliePayment($paymentArray);

    }//end createPayment()


    /**
     * Creates payment for given marriage.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @return array
     */
    public function createPaymentHandler(?array $data=[], ?array $configuration=[]): array
    {
        $this->pluginLogger->debug('createPaymentHandler triggered');
        $this->data          = $data;
        $this->configuration = $configuration;

        if ($this->data['method'] !== 'GET') {
            $this->pluginLogger->error('Not a GET request');

            throw new MethodNotAllowedHttpException('This method is not supported.');
        }//end if

        $payment = $this->createPayment();

        if ($payment !== null) {
            $this->data['response'] = new Response(\Safe\json_encode($payment), 200);
        }

        return $this->data;

    }//end createPaymentHandler()


}//end class
