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

class AssentService
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
     * @param EntityManagerInterface $entityManager          The Entity Manager
     * @param Security               $security               The Security
     * @param GatewayResourceService $grService              The Gateway Resource Service
     * @param CacheService           $cacheService           The Cache Service
     * @param LoggerInterface        $pluginLogger           The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        GatewayResourceService $grService,
        CacheService $cacheService,
        LoggerInterface        $pluginLogger
    ) {
        $this->entityManager = $entityManager;
        $this->security      = $security;
        $this->serializer    = new Serializer();
        $this->grService     = $grService;
        $this->cacheService  = $cacheService;
        $this->pluginLogger           = $pluginLogger;

    }//end __construct()


    /**
     * This function creates a person object for the given BRP person.
     *
     * @TODO: Probably we want to move this to a mapping.
     *
     * @param array        $huwelijk  The marriage array given by the frontend.
     * @param ObjectEntity $brpPerson The person from the BRP.
     *
     * @return ObjectEntity The person in the contact.
     */
    public function createPerson(array $huwelijk, ?ObjectEntity $brpPerson=null, ?ObjectEntity $person=null): ?ObjectEntity
    {
        $personSchema = $this->grService->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json', 'common-gateway/huwelijksplanner-bundle');

        if ($person === null) {
            $person = new ObjectEntity($personSchema);
        }

        if ($brpPerson) {
            $naam                                       = $brpPerson->getValue('naam');
            $verblijfplaats                             = $brpPerson->getValue('verblijfplaats');
            $verblijfplaats && $landVanwaarIngeschreven = $verblijfplaats->getValue('landVanwaarIngeschreven');
        }//end if

        $person->hydrate(
            [
                'bronorganisatie'       => '99999',
                // @TODO
                'klantnummer'           => '99999',
                // @TODO
                'websiteUrl'            => 'www.example.com',
                // @TODO
                'voornaam'              => isset($naam) && $naam ? $naam->getValue('voornamen') : $this->security->getUser()->getFirstName(),
                'voorvoegselAchternaam' => isset($naam) && $naam ? $naam->getValue('voorvoegsel') : null,
                'achternaam'            => isset($naam) && $naam ? $naam->getValue('geslachtsnaam') : $this->security->getUser()->getLastName(),
                'adressen'              => [
                    [
                        'naam'                 => isset($naam) && $naam ? 'Adres van '.$naam->getValue('voornamen') : 'Adres van '.$this->security->getUser()->getFirstName(),
                        'straatnaam'           => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('straat') : null,
                        'huisnummer'           => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('huisnummer') : null,
                        'huisletter'           => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('huisletter') : null,
                        'huisnummertoevoeging' => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('huisnummertoevoeging') : null,
                        'postcode'             => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('postcode') : null,
                        'woonplaatsnaam'       => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('woonplaats') : null,
                        'landcode'             => isset($landVanwaarIngeschreven) && $landVanwaarIngeschreven ? $landVanwaarIngeschreven->getValue('code') : null,
                    ],
                ],
                'subject'               => $brpPerson && $brpPerson->getSelf(),
                'subjectType'           => 'natuurlijk_persoon',
                'subjectIdentificatie'  => [
                    'inpBsn'                   => $brpPerson ? $brpPerson->getValue('burgerservicenummer') : $this->security->getUser()->getPerson(),
                    'inpANummer'               => $brpPerson !== null ? $brpPerson->getValue('aNummer') : null,
                    'geslachtsnaam'            => isset($naam) && $naam ? $naam->getValue('geslachtsnaam') : null,
                    'voorvoegselGeslachtsnaam' => isset($naam) ? $naam && $naam->getValue('voorvoegsel') : null,
                    'voorletters'              => isset($naam) && $naam ? $naam->getValue('voorletters') : null,
                    'voornamen'                => isset($naam) && $naam ? $naam->getValue('voornamen') : $this->security->getUser()->getFirstName(),
                    'geslachtsaanduiding'      => $brpPerson ? $brpPerson->getValue('geslachtsaanduiding') : null,
                    // 'geboortedatum' => null, @TODO
                    // 'verblijfsadres' => null, @TODO
                    // 'subVerblijfBuitenland' => null, @TODO
                ],
            ]
        );

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        return $person;

    }//end createPerson()


    /**
     * This function adds data from a BRP person on updating the assent with status 'granted'.
     *
     * @param array $data   The data from the gateway.
     * @param array $config The configuration array.
     *
     * @return array The updated data.
     *
     * @throws \Safe\Exceptions\JsonException
     */
    public function updateAssentHandler(array $data, array $config)
    {
        $brpSchema = $this->grService->getSchema('https://vng.brp.nl/schemas/brp.ingeschrevenPersoon.schema.json', 'common-gateway/huwelijksplanner-bundle');

        if ($data['method'] !== 'PATCH') {
            return $data;
        }

        $assent = $this->entityManager->getRepository('App:ObjectEntity')->find($data['path']['id']);
        if ($assent instanceof ObjectEntity === false) {
            throw new NotFoundHttpException("The assent with id {$data['path']['id']} was not found.");
        }

        $assentData = $assent->toArray();

        if ($assentData['status'] === 'granted'
            && ($assentData['contact'] === false
            || $assentData['contact']['klantnummer'] === false)
        ) {
            // get brp person from the logged in user
            $brpPersons = $this->cacheService->searchObjects(null, ['burgerservicenummer' => $this->security->getUser()->getPerson()], [$brpSchema->getId()->toString()])['results'];
            $brpPerson  = null;
            if (count($brpPersons) === 1) {
                $brpPerson = $this->entityManager->find('App:ObjectEntity', $brpPersons[0]['_self']['id']);
            }//end if

            $person = $assent->getValue('contact');

            if ($person === false) {
                $person = $this->createPerson([], $brpPerson, null);
                $assent->hydrate(['contact' => $person]);

            } else {
                $this->createPerson([], $brpPerson, $person);
            }

            $this->entityManager->persist($assent);
            $this->entityManager->flush();
        }
        $cacheAssent = $this->cacheService->getObject($assent->getId()->toString());

        $data['response'] = new Response(\Safe\json_encode($cacheAssent), 200, ['content-type' => 'application/json']);

        return $data;

    }//end updateAssentHandler()


}//end class
