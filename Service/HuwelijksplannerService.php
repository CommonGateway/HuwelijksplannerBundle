<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use App\Service\ObjectEntityService;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Exception;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

/**
 * This service holds al the logic for the huwelijksplanner plugin.
 */
class HuwelijksplannerService
{
    private EntityManagerInterface $entityManager;
    private ObjectEntityService $objectEntityService;
    private array $data;
    private array $configuration;

    /**
     * @param ObjectEntityService    $objectEntityService
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        ObjectEntityService $objectEntityService,
        EntityManagerInterface $entityManager
    ) {
        $this->objectEntityService = $objectEntityService;
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
     * @throws Exception
     *
     * @return array
     */
    public function huwelijksplannerCalendarHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        $begin = new DateTime($this->data['parameters']->get('start'));
        $end = new DateTime($this->data['parameters']->get('stop'));

        $interval = new DateInterval($this->data['parameters']->get('interval'));
        $period = new DatePeriod($begin, $interval, $end);

        $resultArray = [];
        foreach ($period as $currentDate) {
            // start voorbeeld code
            $dayStart = clone $currentDate;
            $dayStop = clone $currentDate;

            $dayStart->setTime(9, 0);
            $dayStop->setTime(17, 0);

            if ($currentDate->format('Y-m-d H:i:s') >= $dayStart->format('Y-m-d H:i:s') && $currentDate->format('Y-m-d H:i:s') < $dayStop->format('Y-m-d H:i:s')) {
                $resourceArray = $this->data['parameters']->get('resources_could');
            } else {
                $resourceArray = [];
            }

            // end voorbeeld code
            $resultArray[$currentDate->format('Y-m-d')][] = [
                'start'     => $currentDate->format('Y-m-d\TH:i:sO'),
                'stop'      => $currentDate->add($interval)->format('Y-m-d\TH:i:sO'),
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
     *
     * @throws Exception
     *
     * @return string|null
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
     * @param ObjectEntity         $huwelijk
     * @param PersistentCollection $partners
     *
     * @throws Exception
     *
     * @return ObjectEntity|null
     */
    public function huwelijkPartners(ObjectEntity $huwelijk): ?ObjectEntity
    {
        foreach ($huwelijk->getValue('partners') as $partner) {
            var_dump($partner);
            var_dump($partner['requester']);
            $requester = $partner['requester'];
            $person = $partner['person'];
            $subjectIdentificatie = $person['subjectIdentificatie'];
            $klantBsn = $subjectIdentificatie['inpBsn'];

            $partner->setValue('status', $requester === $klantBsn ? 'granted' : 'requested');
            $this->entityManager->persist($partners);

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
     * @throws Exception
     *
     * @return array
     */
    public function huwelijksplannerAssentHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if ($this->data['parameters']->getMethod() !== 'PUT') {
            return $this->data;
        }

        var_dump('jojojoojo');
        var_dump($this->data['response']['id']);

        if (!array_key_exists('huwelijksEntityId', $this->configuration)) {
            return $this->data;
        }
        $huwelijkEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['huwelijksEntityId']);

        if (array_key_exists('id', $this->data['response']) &&
            $huwelijk = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $huwelijkEntity, 'id' => $this->data['response']['id']])) {
            if ($partners = $huwelijk->getValue('partners')) {
                var_dump($huwelijk->getValue('partners'));

                $huwelijk = $this->huwelijkPartners($huwelijk);
            }

            $this->entityManager->persist($huwelijk);

            var_dump($this->data['response']['id']);

            var_dump($huwelijk->toArray());
            exit();
        }

        return $this->data['response'];
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws Exception
     *
     * @return array
     */
    public function addPartnerFromUser(Security $security): ObjectEntity
    {
        $natuurlijkPersoonEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['natuurlijkPersoonEntityId']);
        $natuurlijkPersoon = new ObjectEntity($natuurlijkPersoonEntity);
        $natuurlijkPersoon->setValue('inpBsn', $security->getUser()->getUserIdentifier());
        $natuurlijkPersoon->setValue('geslachtsnaam', $security->getUser()->getLastName());
        $natuurlijkPersoon->setValue('voorvoegselGeslachtsnaam', null);
        $natuurlijkPersoon->setValue('voorletters', null);
        $natuurlijkPersoon->setValue('voornamen', $security->getUser()->getFirstName());
        $natuurlijkPersoon->setValue('geslachtsaanduiding', null);
        $natuurlijkPersoon->setValue('geboortedatum', null);
        $natuurlijkPersoon->setValue('verblijfsadres', null);
        $this->entityManager->persist($natuurlijkPersoon);
//        var_dump($natuurlijkPersoon->toArray());die();

        $klantEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['klantEntityId']);
        $klant = new ObjectEntity($klantEntity);
        $klant->setValue('bronorganisatie', "99999");
        $klant->setValue('klantnummer', '99999');
        $klant->setValue('websiteUrl', "wwww.example.com");
        $klant->setValue('voornaam', $security->getUser()->getFirstName());
        $klant->setValue('voorvoegselAchternaam', null);
        $klant->setValue('achternaam', $security->getUser()->getLastName());
        $klant->setValue('subjectIdentificatie', $natuurlijkPersoon);
        $klant->setValue('subjectType', 'natuurlijk_persoon');
        $this->entityManager->persist($klant);
        var_dump($klant->toArray());die();


        $assentEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['assentEntityId']);
        $assent = new ObjectEntity($assentEntity);
        $assent->setValue('name', $security->getUser()->getName());
        $assent->setValue('description', null);
        $assent->setValue('property', null);
        $assent->setValue('contact', $klant);
        $assent->setValue('person', $klant);
        $assent->setValue('status', null);
        $assent->setValue('requester', $security->getUser()->getUserIdentifier());

        $this->entityManager->persist($assent);
//        $this->entityManager->flush();

//        var_dump($assent->toArray());

        return $assent;
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws Exception
     *
     * @return array
     */
    public function huwelijksplannerCreateHandler(array $data, array $configuration, Security $security): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if ($this->data['parameters']->getMethod() !== 'POST') {
            return $this->data;
        }

        if (!array_key_exists('huwelijksEntityId', $this->configuration)) {
            return $this->data;
        }
        $huwelijkEntity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['huwelijksEntityId']);

        if (array_key_exists('id', $this->data['response']) &&
            $huwelijk = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $huwelijkEntity, 'id' => $this->data['response']['id']])) {

            if (!$huwelijk->getValue('ceremonie')) {
                throw new GatewayException('Ceremonie is null', null, null, [
                    'data'         => $huwelijk->getValue('ceremonie'), 'path' => 'ceremonie',
                    'responseType' => Response::HTTP_BAD_REQUEST,
                ]);
            }

            if (!$huwelijk->getValue('producten')) {
                throw new GatewayException('Producten is null', null, null, [
                    'data'         => $huwelijk->getValue('producten'), 'path' => 'producten',
                    'responseType' => Response::HTTP_BAD_REQUEST,
                ]);
            }

            if (!$huwelijk->getValue('moment')) {
                throw new GatewayException('Moment is null', null, null, [
                    'data'         => $huwelijk->getValue('moment'), 'path' => 'moment',
                    'responseType' => Response::HTTP_BAD_REQUEST,
                ]);
            }
            // check producten
            // check ceremonie
            // check moment
            // check locatie if balie huwelijk

            $requestPartnerAssent[] = $this->addPartnerFromUser($security);
            $huwelijk->setValue('partners', $requestPartnerAssent);
            $this->entityManager->persist($huwelijk);
//            $this->entityManager->flush();
            var_dump($huwelijk->getValue('partners')[0]->toArray());
//            var_dump($huwelijk->toArray());

            $this->data['response'] = $huwelijk->toArray();

        }

        var_dump($this->data['response']);
        return $this->data;
    }

    /**
     * Handles Huwelijkslnner actions.
     *
     * @param array $data
     * @param array $configuration
     *
     * @throws LoaderError|RuntimeError|SyntaxError|TransportExceptionInterface
     *
     * @return array
     */
    public function huwelijksplannerCheckHandler(array $data, array $configuration): array
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
