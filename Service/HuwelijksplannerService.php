<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * This service holds al the logic for the huwelijksplanner plugin.
 */
class HuwelijksplannerService
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private RequestStack $requestStack;
    private Request $request;
    private array $data;
    private array $configuration;

    /**
     * @param ObjectEntityService $objectEntityService
     * @param RequestStack $requestStack
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        ObjectEntityService    $objectEntityService,
        RequestStack           $requestStack,
        EntityManagerInterface $entityManager
    )
    {
        $this->objectEntityService = $objectEntityService;
        $this->request = $requestStack->getCurrentRequest();
        $this->entityManager = $entityManager;
        $this->data = [];
        $this->configuration = [];
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     * @throws Exception
     *
     */
    public function huwelijksplannerHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $begin = new DateTime($this->request->get('start'));
        $end = new DateTime($this->request->get('stop'));

        $interval = new DateInterval($this->request->get('interval'));
        $period = new DatePeriod($begin, $interval, $end);

        $resultArray = [];
        foreach ($period as $currentDate) {
            // start voorbeeld code
            $dayStart = clone $currentDate;
            $dayStop = clone $currentDate;

            $dayStart->setTime(9, 0);
            $dayStop->setTime(17, 0);

            if ($currentDate->format('Y-m-d H:i:s') >= $dayStart->format('Y-m-d H:i:s') && $currentDate->format('Y-m-d H:i:s') < $dayStop->format('Y-m-d H:i:s')) {
                $resourceArray = $this->request->get('resources_could');
            } else {
                $resourceArray = [];
            }

            // end voorbeeld code
            $resultArray[$currentDate->format('Y-m-d')][] = [
                'start' => $currentDate->format('Y-m-d\TH:i:sO'),
                'stop' => $currentDate->add($interval)->format('Y-m-d\TH:i:sO'),
                'resources' => $resourceArray,
            ];
        }

        $this->data['response'] = $resultArray;

        return $this->data;
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param ObjectEntity $partner
     * @return string|null
     * @throws Exception
     */
    public function mailConsentingPartner(ObjectEntity $partner): ?string
    {
        $person = $partner->getValue('person');
        $phoneNumbers = $person->getValue('telefoonnummers');
        $emailAddresses = $person->getValue('emails');

        if (count($phoneNumbers) > 0 || count($emailAddresses) > 0) {
            // sent email or phoneNumber

            var_dump('hier mail of sms versturen en een secret genereren');
        } else {
            throw new GatewayException('Email or phone number must be present', null, null, ['data' => 'telefoonnummers and/or emails', 'path' => 'Request body', 'responseType' => Response::HTTP_BAD_REQUEST]);
        }

        return null;
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param ObjectEntity $huwelijk
     * @param PersistentCollection $partners
     * @return ObjectEntity|null
     * @throws Exception
     */
    public function huwelijkPartners(ObjectEntity $huwelijk, PersistentCollection $partners): ?ObjectEntity
    {
        foreach ($partners as $partner) {
            $requester = $partner->getValue('requester');
            $person = $partner->getValue('person');
            $subjectIdentificatie = $person->getValue('subjectIdentificatie');
            $klantBsn = $subjectIdentificatie->getValue('inpBsn');


            $partner->setValue('status', $requester === $klantBsn ? 'granted' : 'requested');

            if ($klantBsn > $requester || $klantBsn < $requester) {
                $this->mailConsentingPartner($partner);
            }

        }

        return $huwelijk;
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     * @throws Exception
     *
     */
    public function huwelijksplannerAssentHandler(array $data, array $configuration): array
    {
        var_dump('jooo');
        $this->data = $data;
        $this->configuration = $configuration;

        $huwelijkEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['huwelijkEntityId']);

//        var_dump($this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $huwelijkEntity, 'id' => $this->data['response']['id']])->toArray());

        if (array_key_exists('id', $this->data['response']) &&
            $huwelijk = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $huwelijkEntity, 'id' => $this->data['response']['id']])) {


            if ($partners = $huwelijk->getValue('partners')) {
                $huwelijk = $this->huwelijkPartners($huwelijk, $partners);
            }

            var_dump($huwelijk->toArray());
            die();


        }

        return $this->data;
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     */
    public function HuwelijksplannerCheckHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Check if the incommming data exisits and is a huwelijk object
        if (
            in_array('id', $this->data) &&
            $huwelijk = $this->objectEntityService->getObject(null, $this->data['id']) &&
                $huwelijk->getEntity()->getName() == 'huwelijk') {
            return $this->checkHuwelijk($huwelijk)->toArray();
        }

        return $data;
    }

    public function checkHuwelijk(ObjectEntity $huwelijk): ObjectEntity
    {
        $checklist = [];

        // Check partners
        if (count($huwelijk->getValueByAttribute('partners')) < 2) {
            $checklist['partners'] = 'Voor een huwelijk/partnerschap zijn minimaal 2 partners nodig';
        } elseif (count($huwelijk->getValueByAttribute('partners')) > 2) {
            $checklist['partners'] = 'Voor een huwelijk/partnerschap kunnen maximaal 2 partners worden opgegeven';
        }
        // Check getuigen
        // @todo eigenlijk is het minimaal 1 en maximaal 2 getuigen per partner
        if (count($huwelijk->getValueByAttribute('getuigen')) < 2) {
            $checklist['getuigen'] = 'Voor een huwelijk/partnerschap zijn minimaal 2 getuigen nodig';
        } elseif (count($huwelijk->getValueByAttribute('getuigen')) > 4) {
            $checklist['getuigen'] = 'Voor een huwelijk/partnerschap kunnen maximaal 4 getuigen worden opgegeven';
        }
        // Kijken naar locatie
        if (!$huwelijk->getValueByAttribute('locatie')) {
            $checklist['locatie'] = 'Nog geen locatie opgegeven';
        }
        // Kijken naar ambtenaar
        if (!$huwelijk->getValueByAttribute('ambtenaar')) {
            $checklist['ambtenaar'] = 'Nog geen ambtenaar opgegeven';
        }
        // @todo trouwdatum minimaal 2 weken groter dan aanvraag datum

        $huwelijk->setValue('checklist', $checklist);

        $this->objectEntityService->saveObject($huwelijk);

        return $huwelijk;
    }
}
