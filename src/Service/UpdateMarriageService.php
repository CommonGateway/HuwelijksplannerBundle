<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Serializer;

class UpdateMarriageService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var Security
     */
    private Security $security;

    /**
     * @var Serializer
     */
    private Serializer $serializer;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $grService;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * @var PaymentService
     */
    private PaymentService $paymentService;

    /**
     * @var UpdateChecklistService
     */
    private UpdateChecklistService $checklistService;


    /**
     * @param EntityManagerInterface $entityManager    The Entity Manager
     * @param Security               $security         The Security
     * @param GatewayResourceService $grService        The Gateway Resource Service
     * @param CacheService           $cacheService     The Cache Service
     * @param LoggerInterface        $pluginLogger     The Logger Interface
     * @param PaymentService         $paymentService   The Payment Service
     * @param UpdateChecklistService $checklistService The Update Checklist Service
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        GatewayResourceService $grService,
        CacheService $cacheService,
        LoggerInterface $pluginLogger,
        PaymentService $paymentService,
        UpdateChecklistService $checklistService
    ) {
        $this->entityManager    = $entityManager;
        $this->security         = $security;
        $this->serializer       = new Serializer();
        $this->grService        = $grService;
        $this->cacheService     = $cacheService;
        $this->pluginLogger     = $pluginLogger;
        $this->paymentService   = $paymentService;
        $this->checklistService = $checklistService;

    }//end __construct()


    /**
     * This function calculates the costs and updates the checklist service.
     *
     * @param array $data   The data from the gateway.
     * @param array $config The configuration array.
     *
     * @return array The updated data.
     *
     * @throws \Safe\Exceptions\JsonException
     */
    public function updateMarriageHandler(array $data, array $config)
    {
        $this->data = $data;

        if ($this->data['method'] === 'GET') {
            $this->pluginLogger->error('Not a GET request');

            return $this->data;
        }//end if

        $response = json_decode($this->data['response']->getContent(), true);

        $huwelijk = $this->entityManager->getRepository('App:ObjectEntity')->find($response['_self']['id']);
        if ($huwelijk instanceof ObjectEntity === false) {
            throw new NotFoundHttpException("The huwelijk with id {$response['_self']['id']} was not found.");
        }

        $huwelijkArray = $huwelijk->toArray();

        // Get all prices from the products
        $productPrices = $this->paymentService->getSDGProductPrices($huwelijkArray);
        // Calculate new price
        $huwelijk->setValue('kosten', 'EUR '.(string) $this->paymentService->calculatePrice($productPrices, 'EUR'));
        $this->entityManager->persist($huwelijk);
        $this->entityManager->flush();

        $this->checklistService->checkHuwelijk($huwelijk);

        $cacheHuwelijk = $this->cacheService->getObject($huwelijk->getId()->toString());

        $data['response'] = new Response(\Safe\json_encode($cacheHuwelijk), 200, ['content-type' => 'application/json']);

        return $data;

    }//end updateMarriageHandler()


}//end class
