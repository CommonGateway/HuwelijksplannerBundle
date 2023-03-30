<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * This service holds al the logic for creating the marriage request object.
 */
class InvitePartnerService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $gatewayResourceService;

    /**
     * @var HandleAssentService
     */
    private HandleAssentService $handleAssentService;

    /**
     * @var UpdateChecklistService
     */
    private UpdateChecklistService $updateChecklistService;

    /**
     * @var Security
     */
    private Security $security;

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
     * @param EntityManagerInterface $entityManager          The Entity Manager
     * @param GatewayResourceService $gatewayResourceService The Gateway Resource Service
     * @param HandleAssentService    $handleAssentService    The Handle Assent Service
     * @param UpdateChecklistService $updateChecklistService The Update Checklist Service
     * @param Security               $security               The Security
     * @param LoggerInterface        $pluginLogger           The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GatewayResourceService $gatewayResourceService,
        HandleAssentService $handleAssentService,
        UpdateChecklistService $updateChecklistService,
        Security $security,
        LoggerInterface $pluginLogger
    ) {
        $this->entityManager          = $entityManager;
        $this->gatewayResourceService = $gatewayResourceService;
        $this->data                   = [];
        $this->configuration          = [];
        $this->handleAssentService    = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security               = $security;
        $this->pluginLogger           = $pluginLogger;

    }//end __construct()


    /**
     * This function validates and creates the huwelijk object and creates an assent for the current user.
     *
     * @param array  $huwelijk The huwelijk array from the request.
     * @param string $id       The id of the huwelijk object.
     *
     * @return ?array The updated huwelijk object as array.
     */
    private function invitePartner(array $huwelijk, string $id): ?array
    {
        $huwelijkObject = $this->entityManager->getRepository('App:ObjectEntity')->find($id);
        if ($huwelijkObject instanceof ObjectEntity === false) {
            $this->pluginLogger->error('Could not find huwelijk with id '.$id);

            $this->data['response'] = 'Could not find huwelijk with id '.$id;

            return $this->data;
        }//end if

        // @TODO check if the requester has already a partner
        // if so throw error else continue
        if (isset($huwelijk['partners']) === true
            && count($huwelijk['partners']) === 1
        ) {
            if (count($huwelijkObject->getValue('partners')) > 1) {
                // @TODO update partner?
                return $huwelijkObject->toArray();
            }//end if

            if (count($huwelijkObject->getValue('partners')) !== 1) {
                $this->pluginLogger->error('You cannot add a partner before the requester is set.');

                $this->data['response'] = 'You cannot add a partner before the requester is set.';

                return $this->data;
            }//end if

            $personSchema = $this->gatewayResourceService->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json', 'common-gateway/huwelijksplanner-bundle');
            $brpSchema    = $this->gatewayResourceService->getSchema('https://vng.brp.nl/schemas/brp.ingeschrevenPersoon.schema.json', 'common-gateway/huwelijksplanner-bundle');

            if (isset($huwelijk['partners'][0]['contact']['subjectIdentificatie']['inpBsn']) === true) {
                $brpPerson = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity(
                    $brpSchema,
                    ['burgerservicenummer' => $huwelijk['partners'][0]['contact']['subjectIdentificatie']['inpBsn']]
                );
                if ($brpPerson[0] instanceof ObjectEntity === true) {
                    $brpPerson = $brpPerson[0];
                }
            }

            $person = $this->createPerson($huwelijk, $brpPerson);

            // $person = new ObjectEntity($personSchema);
            // $person->hydrate($huwelijk['partners'][0]['contact']);
            // $this->entityManager->persist($person);
            $this->entityManager->flush();

            $partners                      = $huwelijkObject->getValue('partners');
            $requesterAssent['partners'][] = $partners[0]->getId()->toString();
            $requesterAssent['partners'][] = $this->handleAssentService->handleAssent($person, 'partner', $this->data)->getId()->toString();
            $huwelijkObject->hydrate($requesterAssent);

            $this->entityManager->persist($huwelijkObject);
            $this->entityManager->flush();

            $huwelijkObject = $this->updateChecklistService->checkHuwelijk($huwelijkObject);
        }//end if

        return $huwelijkObject->toArray();

    }//end invitePartner()


    /**
     * Creates the marriage request object.
     *
     * @param ?array $data          The data array.
     * @param ?array $configuration The configuration array.
     *
     * @throws Exception
     *
     * @return array The data array
     */
    public function invitePartnerHandler(?array $data=[], ?array $configuration=[]): array
    {
        $this->pluginLogger->debug('invitePartnerHandler triggered');
        $this->data          = $data;
        $this->configuration = $configuration;

        if (in_array('huwelijk', $this->data['parameters']['endpoint']->getPath()) === false) {
            return $this->data;
        }//end if

        if (isset($this->data['parameters']['body']) === false) {
            $this->pluginLogger->error('No data passed');

            return [
                'response' => ['message' => 'No data passed'],
                'httpCode' => 400,
            ];
        }//end if

        if ($this->data['parameters']['method'] !== 'PATCH') {
            $this->pluginLogger->error('Not a PATCH request');

            return $this->data;
        }//end if

        if (isset($this->data['response']['_self']['id']) === false) {
            return $this->data;
        }//end if

        $huwelijk = $this->invitePartner($this->data['parameters']['body'], $this->data['response']['_self']['id']);

        $this->data['response'] = $huwelijk;

        return $this->data;

    }//end invitePartnerHandler()


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
    private function createPerson(array $huwelijk, ?ObjectEntity $brpPerson=null): ?ObjectEntity
    {
        $personSchema = $this->gatewayResourceService->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json', 'common-gateway/huwelijksplanner-bundle');

        if ($brpPerson) {
            $naam                                       = $brpPerson->getValue('naam');
            $verblijfplaats                             = $brpPerson->getValue('verblijfplaats');
            $verblijfplaats && $landVanwaarIngeschreven = $verblijfplaats->getValue('landVanwaarIngeschreven');
        }//end if

        // @TODO check how and if we get the email and phonenumber from the frontend
        if (key_exists('partners', $huwelijk) === true
            && key_exists('person', $huwelijk['partners'][0])
        ) {
            $huwelijkPerson = $huwelijk['partners'][0]['contact'];

            if (key_exists('emails', $huwelijkPerson) === true) {
                $email = $huwelijkPerson['emails'][0]['email'];
            }//end if

            if (key_exists('telefoonnummers', $huwelijkPerson) === true) {
                $phonenumber = $huwelijkPerson['telefoonnummers'][0]['telefoonnummer'];
            }//end if
        }//end if

        $person = new ObjectEntity($personSchema);
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
                'telefoonnummers'       => [
                    [
                        'naam'           => isset($naam) ? 'Telefoonnummer van '.$naam->getValue('voornamen') : 'Emailadres van '.$this->security->getUser()->getFirstName(),
                        'telefoonnummer' => isset($phonenumber) ? $phonenumber : null,
                    ],
                ],
                'emails'                => [
                    [
                        'naam'  => isset($naam) ? 'Emailadres van '.$naam->getValue('voornamen') : 'Emailadres van '.$this->security->getUser()->getFirstName(),
                        'email' => isset($email) ? $email : $this->security->getUser()->getEmail(),
                    ],
                ],
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


}//end class
