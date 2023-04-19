<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Serializer;

class AssentService
{

    private EntityManagerInterface $entityManager;

    private Security $security;

    private Serializer $serializer;

    private GatewayResourceService $grService;

    private CacheService $cacheService;


    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        GatewayResourceService $grService,
        CacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->security      = $security;
        $this->serializer    = new Serializer();
        $this->grService     = $grService;
        $this->cacheService  = $cacheService;

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

        if ($brpPerson) {
            $naam                                       = $brpPerson->getValue('naam');
            $verblijfplaats                             = $brpPerson->getValue('verblijfplaats');
            $verblijfplaats && $landVanwaarIngeschreven = $verblijfplaats->getValue('landVanwaarIngeschreven');
        }//end if

        // @TODO check how and if we get the email and phonenumber from the frontend
        if (key_exists('partners', $huwelijk) === true
            && key_exists('contact', $huwelijk['partners'][0])
        ) {
            $huwelijkPerson = $huwelijk['partners'][0]['contact'];

            if (key_exists('emails', $huwelijkPerson) === true) {
                $email = $huwelijkPerson['emails'][0]['email'];
            }//end if

            if (key_exists('telefoonnummers', $huwelijkPerson) === true) {
                $phonenumber = $huwelijkPerson['telefoonnummers'][0]['telefoonnummer'];
            }//end if
        }//end if

        if ($person === null) {
            $person = new ObjectEntity($personSchema);
        }

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
                $person = null;
            }

            $person = $this->createPerson([], $brpPerson, $person);

            $assent->hydrate(['contact' => $person]);
        }

        $this->entityManager->persist($assent);
        $this->entityManager->flush();

        $data['response'] = new Response(\Safe\json_encode($assent->toArray()), 200, ['content-type' => 'application/json']);

        return $data;

    }//end updateAssentHandler()


}//end class
