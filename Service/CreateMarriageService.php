<?php

namespace CommonGateway\HuwelijksplannerBundle\Service;

use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use App\Exception\GatewayException;
use CommonGateway\CoreBundle\Service\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Psr\Log\LoggerInterface;

/**
 * This service holds al the logic for creating the marriage request object.
 */
class CreateMarriageService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $io;

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
    private LoggerInterface $logger;

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
     * @param CacheService $cacheService The Cache Service
     * @param HandleAssentService    $handleAssentService    The Handle Assent Service
     * @param UpdateChecklistService $updateChecklistService The Update Checklist Service
     * @param Security               $security               The Security
     * @param LoggerInterface $logger The Logger Interface
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheService $cacheService,
        HandleAssentService $handleAssentService,
        UpdateChecklistService $updateChecklistService,
        Security $security,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->cacheService = $cacheService;
        $this->data = [];
        $this->configuration = [];
        $this->handleAssentService = $handleAssentService;
        $this->updateChecklistService = $updateChecklistService;
        $this->security = $security;
        $this->logger = $logger;
    }

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $io
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
    }

    /**
     * Get an schema by reference.
     *
     * @param string $reference The reference to look for
     *
     * @return Schema|null
     */
    public function getSchema(string $reference): ?Schema
    {
        $schema = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $reference]);
        if ($schema === null) {
            $this->logger->error("No schema found for $reference");
            isset($this->io) && $this->io->error("No schema found for $reference");
        }//end if

        return $schema;
    }//end getSchema()

    /**
     * Validate huwelijk type.
     */
    private function validateType(array $huwelijk)
    {
        if (isset($huwelijk['type'])) {
            if (!$typeProductObject = $this->entityManager->getRepository('App:ObjectEntity')->find($huwelijk['type'])) {
                isset($this->io) && $this->io->error('huwelijk.type not found in the databse with given id');
                $this->logger->error('huwelijk.type not found in the databse with given id');

                return ['response' => ['message' => 'huwelijk.type not found in the databse with given id'], 'httpCode' => 400];
            }

            if (!in_array($typeProductObject->getValue('upnLabel'), ['huwelijk', 'Omzetting', 'Partnerschap'])) {
                isset($this->io) && $this->io->error('huwelijk.type.upnLabel is not huwelijk, omzetten or partnerschap');
                $this->logger->error('huwelijk.type.upnLabel is not huwelijk, omzetten or partnerschap');

                return ['response' => ['message' => 'huwelijk.type.upnLabel is not huwelijk, Omzetting or Partnerschap'], 'httpCode' => 400];
            }

            return true;
        } else {
            isset($this->io) && $this->io->error('huwelijk.type is not given');
            $this->logger->error('huwelijk.type is not given');

            return ['response' => ['message' => 'huwelijk.type is not given'], 'httpCode' => 400];
        }
    }//end validateType()

    /**
     * Validate huwelijk type.
     *
     * @return array|bool $huwelijk OR false when invalid huwelijk
     */
    private function validateCeremonie(array $huwelijk)
    {
        if (isset($huwelijk['ceremonie'])) {
            if (!$ceremonieProductObject = $this->entityManager->getRepository('App:ObjectEntity')->find($huwelijk['ceremonie'])) {
                isset($this->io) && $this->io->error('huwelijk.ceremonie not found in the databse with given id');
                $this->logger->error('huwelijk.ceremonie not found in the databse with given id');

                return ['response' => ['message' => 'huwelijk.ceremonie not found in the databse with given id'], 'httpCode' => 400];
            }

            if (!in_array($ceremonieProductObject->getValue('upnLabel'), ['gratis trouwen', 'flits/balliehuwelijk', 'eenvoudig huwelijk', 'uitgebreid huwelijk'])) {
                isset($this->io) && $this->io->error('huwelijk.ceremonie.upnLabel is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk');
                $this->logger->error('huwelijk.ceremonie.upnLabel is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk');

                return ['response' => ['message' => 'huwelijk.ceremonie.upnLabel is not gratis trouwen, flits/balliehuwelijk, eenvoudig huwelijk, uitgebreid huwelijk'], 'httpCode' => 400];
            }

            return true;
        } else {
            isset($this->io) && $this->io->error('huwelijk.ceremonie is not given');
            $this->logger->error('huwelijk.ceremonie is not given');

            return ['response' => ['message' => 'huwelijk.ceremonie is not given'], 'httpCode' => 400];
        }
    }//end validateCeremonie()

    /**
     * This function creates a person object for the given user.
     */
    private function createPerson(array $huwelijk, ?ObjectEntity $brpPerson = null): ?ObjectEntity
    {
        $personSchema = $this->getSchema('https://klantenBundle.commonground.nu/klant.klant.schema.json');

        if ($brpPerson) {
            $naam = $brpPerson->getValue('naam');
            $verblijfplaats = $brpPerson->getValue('verblijfplaats');
            $verblijfplaats && $landVanwaarIngeschreven = $verblijfplaats->getValue('landVanwaarIngeschreven');
        }

        // @TODO check how and if we get the email and phonenumber from the frontend
        if (key_exists('partners', $huwelijk) && key_exists('person', $huwelijk['partners'][0])) {
            $huwelijkPerson = $huwelijk['partners'][0]['person'];

            if (key_exists('emails', $huwelijkPerson)) {
                $email = $huwelijkPerson['emails'][0]['email'];
            }

            if (key_exists('telefoonnummers', $huwelijkPerson)) {
                $phonenumber = $huwelijkPerson['telefoonnummers'][0]['telefoonnummer'];
            }
        }

        $person = new ObjectEntity($personSchema);
        $person->hydrate([
            'voornaam'              => isset($naam) && $naam ? $naam->getValue('voornamen') : $this->security->getUser()->getFirstName(),
            'voorvoegselAchternaam' => isset($naam) && $naam ? $naam->getValue('voorvoegsel') : null,
            'achternaam'            => isset($naam) && $naam ? $naam->getValue('geslachtsnaam') : $this->security->getUser()->getLastName(),
            'telefoonnummers'       => [[
                'naam'  => isset($naam) ? 'Telefoonnummer van '.$naam->getValue('voornamen') : 'Emailadres van '.$this->security->getUser()->getFirstName(),
                'telefoonnummer' => isset($phonenumber) ? $phonenumber : null,
            ]],
            'emails'                => [[
                'naam'  => isset($naam) ? 'Emailadres van '.$naam->getValue('voornamen') : 'Emailadres van '.$this->security->getUser()->getFirstName(),
                'email' => isset($email) ? $email: $this->security->getUser()->getEmail(),
            ]],
            'adressen'             => [[
                'naam' => isset($naam) && $naam ? 'Adres van '.$naam->getValue('voornamen') : 'Adres van '.$this->security->getUser()->getFirstName(),
                'straatnaam' => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('straat') : null,
                'huisnummer' => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('huisnummer') : null,
                'huisletter' => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('huisletter') : null,
                'huisnummertoevoeging' => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('huisnummertoevoeging') : null,
                'postcode' => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('postcode') : null,
                'woonplaatsnaam' => isset($verblijfplaats) && $verblijfplaats ? $verblijfplaats->getValue('woonplaats') : null,
                'landcode' => isset($landVanwaarIngeschreven) && $landVanwaarIngeschreven ? $landVanwaarIngeschreven->getValue('code') : null,
            ]],
            'subject'              => $brpPerson && $brpPerson->getSelf(),
            'subjectType'          => 'natuurlijk_persoon',
            'subjectIdentificatie' => [
                'inpBsn' => $brpPerson ? $brpPerson->getValue('burgerservicenummer') : $this->security->getUser()->getPerson(),
                'inpANummer' => $brpPerson !== null ? $brpPerson->getValue('aNummer') : null,
                'geslachtsnaam' => isset($naam) && $naam ? $naam->getValue('geslachtsnaam') : null,
                'voorvoegselGeslachtsnaam' => isset($naam) ? $naam && $naam->getValue('voorvoegsel') : null,
                'voorletters' => isset($naam) && $naam ? $naam->getValue('voorletters') : null,
                'voornamen' => isset($naam) && $naam ? $naam->getValue('voornamen') : $this->security->getUser()->getFirstName(),
                'geslachtsaanduiding' => $brpPerson ? $brpPerson->getValue('geslachtsaanduiding') : null,
//                'geboortedatum' => null, @TODO
//                'verblijfsadres' => null, @TODO
//                'subVerblijfBuitenland' => null, @TODO
            ]
        ]);
        $this->entityManager->persist($person);
        $this->entityManager->flush();

        return $person;
    }//end createPerson()

    /**
     * This function validates and creates the huwelijk object
     * and creates an assent for the current user.
     */
    private function createMarriage(array $huwelijk): ?array
    {
        $huwelijkSchema = $this->getSchema('https://huwelijksplanner.nl/schemas/hp.huwelijk.schema.json');
        $brpSchema = $this->getSchema('https://vng.brp.nl/schemas/brp.ingeschrevenPersoon.schema.json');

        $huwelijkObject = new ObjectEntity($huwelijkSchema);

        // @TODO validate moment and location
        if ($this->validateType($huwelijk) && $this->validateCeremonie($huwelijk)) {
            $huwelijkArray = [
                'locatie' => key_exists('locatie', $huwelijk) ? $huwelijk['locatie'] : null,
                'type'    => $huwelijk['type'],
                'moment'  => $huwelijk['moment'],
                'ceremonie' => $huwelijk['ceremonie']
            ];

            $huwelijkObject->hydrate($huwelijkArray);
            $this->entityManager->persist($huwelijkObject);

            // get brp person from the logged in user
            $brpPersons = $this->cacheService->searchObjects(null, ['burgerservicenummer' => $this->security->getUser()->getPerson()], [$brpSchema->getId()->toString()])['results'];
            if (count($brpPersons) === 1) {
                $brpPerson = $this->entityManager->find('App:ObjectEntity', $brpPersons[0]['_self']['id']);
            }

            // create person from logged in user and if we have a brp person we set those values
            // if not we set the values from the security object
            $person = $this->createPerson($huwelijk, $brpPerson ?? null);

            // creates an assent and add the person to the partners of this merriage
            $requesterAssent['partners'][] = $this->handleAssentService->handleAssent($person, 'requester', $this->data);
            $huwelijkObject->hydrate($requesterAssent);

            // @TODO update checklist with moment
//            $huwelijkObject = $this->updateChecklistService->checkHuwelijk($huwelijkObject);

            $this->entityManager->persist($huwelijkObject);
            $this->entityManager->flush();

            return $huwelijkObject->toArray();
        }

        return ['response' => ['message' => 'Validation failed'], 'httpCode' => 400];

        // @TODO delete the huwelijk object if validation failed
    }//end createMarriage()

    /**
     * Creates the marriage request object.
     *
     * @param ?array $data
     * @param ?array $configuration
     *
     * @throws Exception
     *
     * @return ?array
     */
    public function createMarriageHandler(?array $data = [], ?array $configuration = []): ?array
    {
        isset($this->io) && $this->io->success('createMarriageHandler triggered');
        $this->data = $data;
        $this->configuration = $configuration;

        if (!isset($this->data['body'])) {
            isset($this->io) && $this->io->error('No data passed'); // @TODO throw exception ?
            $this->logger->error('No data passed');

            return ['response' => ['message' => 'No data passed'], 'httpCode' => 400];
        }

        if ($this->data['method'] !== 'POST') {
            isset($this->io) && $this->io->error('Not a POST request');
            $this->logger->error('Not a POST request');

            return ['response' => ['message' => 'Not a POST request'], 'httpCode' => 400];
        }

        $huwelijk = $this->createMarriage($this->data['body']);

        $this->data['response'] = new Response(
            json_encode($huwelijk),
            Response::HTTP_OK,
            ['content-type' => 'json']
        );

        return $this->data;
    }//end createMarriageHandler()
}
