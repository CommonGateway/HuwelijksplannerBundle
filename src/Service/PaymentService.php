<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Money\Currency;
use Money\Money;
use Ramsey\Uuid\Uuid;

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
     * @var SessionInterface
     */
    private SessionInterface $session;

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
     * @param SessionInterface       $session                The session.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CallService $callService,
        SynchronizationService $syncService,
        GatewayResourceService $gatewayResourceService,
        LoggerInterface $pluginLogger,
        SessionInterface $session
    ) {
        $this->entityManager          = $entityManager;
        $this->callService            = $callService;
        $this->syncService            = $syncService;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->pluginLogger           = $pluginLogger;
        $this->session                = $session;

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
     * @param string|null $productId ID of a product.
     *
     * @return array|null Product object.
     */
    private function getProductObject(?string $productId): ?array
    {
        $productObject = $this->entityManager->getRepository('App:ObjectEntity')->find($productId);
        if ($productObject instanceof ObjectEntity === false) {
            return null;
        }//end if

        return $productObject->toArray();

    }//end getProductObject()


    /**
     * Get product prices from this marriage.
     *
     * @param array $products The products array from the marriage.
     *
     * @return array $productPrices Array of all product prices.
     */
    public function getProductArrayPrices(array $products): array
    {
        $productPrices = [];

        foreach ($products as $extraProduct) {
            // @todo move this to validation
            if (is_array($extraProduct) === false) {
                $extraProduct = $this->getProductObject($extraProduct);
            }//end if

            if (empty($extraProduct) === true) {
                continue;
            }//end if

            if (isset($extraProduct['vertalingen'][0]['kosten']) === false) {
                continue;
            }//end if

            $productPrices[] = $extraProduct['vertalingen'][0]['kosten'];
        }//end foreach

        return $productPrices;

    }//end getProductArrayPrices()


    /**
     * Get product prices from this marriage.
     *
     * @param array $huwelijk Huwelijk object as array.
     *
     * @return array $productPrices Array of all product prices.
     */
    public function getSDGProductPrices(array $huwelijk): array
    {
        $productArrayPrices = [];
        $productPrices      = [];

        foreach ($huwelijk as $key => $value) {
            if (in_array($key, ['type', 'ceremonie', 'locatie', 'ambtenaar', 'producten']) === false) {
                continue;
            }//end if

            if ($key === 'producten') {
                $productArrayPrices = $this->getProductArrayPrices($value);

                continue;
            }//end if

            // @todo move this to validation
            if (is_array($value) === false) {
                $value = $this->getProductObject($value);
            }//end if

            if (empty($value) === true) {
                continue;
            }//end if

            if (isset($value['vertalingen'][0]['kosten']) === false) {
                continue;
            }//end if

            $productPrices[] = $value['vertalingen'][0]['kosten'];
        }//end foreach

        return array_merge($productPrices, $productArrayPrices);

    }//end getSDGProductPrices()


    /**
     * Calculates total price with given prices and currency.
     *
     * @param array       $prices   Array of prices to accumulate.
     * @param string|null $currency ISO 4271 currency.
     *
     * @return string Total price after acummulation.
     */
    public function calculatePrice(array $prices, ?string $currency='EUR'): string
    {
        $currency   = new Currency($currency);
        $totalPrice = new Money(0, $currency);

        foreach ($prices as $price) {
            $price = str_replace('EUR ', '', $price);
            if ($price > 0) {
                $totalPrice = $totalPrice->add(new Money($price, $currency));
            }
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
     * @return array|null Syncrhonization object or a error repsonse or null.
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
     * Validates huwelijk id in query and gets object.
     *
     * @param array $query Paramters from a request.
     *
     * @throws BadRequestHttpException If id not found or valid.
     *
     * @return ObjectEntity Huwelijk object.
     */
    private function validateHuwelijkId(array $query): ?ObjectEntity
    {
        if (isset($query['resource']) === false) {
            return null;
        }//end if

        if (Uuid::isValid($query['resource']) === false) {
            throw new BadRequestHttpException('False id in the query parameter resource.');
        }

        $huwelijkObject = $this->entityManager->find('App:ObjectEntity', $query['resource']);
        if ($huwelijkObject instanceof ObjectEntity === false) {
            throw new BadRequestHttpException('Cannot find huwelijk with given id: '.$query['resource']);
        }//end if

        return $huwelijkObject;

    }//end validateHuwelijkId()


    /**
     * Creates a payment object.
     *
     * @return array|null Payment object as array or null.
     */
    public function createPayment(): ?array
    {
        // @TODO add the values amount from huwelijk object etc to array
        $paymentSchema = $this->gatewayResourceService->getSchema('https://huwelijksplanner.nl/schemas/hp.mollie.schema.json', 'common-gateway/huwelijksplanner-bundle');

        $redirectUrl = null;
        $application = $this->entityManager->getRepository('App:Application')->findOneBy(['reference' => 'https://huwelijksplanner.nl/application/hp.frontend.application.json']);
        if ($application !== null && $application->getDomains() !== null && count($application->getDomains()) > 0) {
            $domain      = $application->getDomains()[0];
            $redirectUrl = 'https://'.$domain.'/voorgenomen-huwelijk/betalen/betaalstatus-verificatie';
        }

        $huwelijkObject = $this->validateHuwelijkId($this->data['query']);
        if ($huwelijkObject === null) {
            return null;
        }

        // Get all prices from the products
        $productPrices = $this->getSDGProductPrices($huwelijkObject->toArray());

        $paymentObject = new ObjectEntity($paymentSchema);
        $paymentArray  = [
            'amount'      => [
                'currency' => 'EUR',
                'value'    => $this->calculatePrice($productPrices, 'EUR'),
        // Calculate new price
            ],
            'description' => 'Payment made for huwelijk with id: '.$huwelijkObject->getId()->toString(),
            'redirectUrl' => $redirectUrl,
            'webhookUrl'  => $this->configuration['webhookUrl'],
            'method'      => $this->configuration['method'],
            'status'      => 'paid',
            // @TODO temporary set the status to paid
        ];
        $paymentObject->hydrate($paymentArray);
        $this->entityManager->persist($paymentObject);
        $this->entityManager->flush();

        // return $this->createMolliePayment($paymentArray);
        // todo: temporary, redirect to return [redirectUrl]. Instead of this $paymentArray and return^
        return [
            'paymentId'   => $paymentObject->getId()->toString(),
            'redirectUrl' => $paymentObject->getValue('redirectUrl'),
        // @TODO set redirectUrl to the checkout url
        ];

    }//end createPayment()


    /**
     * Creates payment for given marriage.
     *
     * @param ?array $data          Data this service might need from a Action.
     * @param ?array $configuration Configuraiton this service might need from a Action.
     *
     * @return array Response array that will be returned to RequestService.
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

        // todo: temp disabled
        // If we dont have a checkout url from mollie return a 502.
        // if (isset($payment['_links']['checkout']) === false) {
        // return [
        // 'response' => [
        // 'message' => 'Payment object created from mollie but no checkout url provided',
        // 'status'  => 502,
        // ],
        // ];
        // }//end if
        if ($payment !== null) {
            $this->data['response'] = new Response(json_encode($payment), 200);
            // $this->data['response'] = new Response(\Safe\json_encode(['checkout' => $payment['_links']['checkout']]), 200);
        }

        return $this->data;

    }//end createPaymentHandler()


}//end class
