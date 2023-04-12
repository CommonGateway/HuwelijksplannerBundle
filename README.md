# HuwelijksplannerBundle [![Codacy Badge](https://app.codacy.com/project/badge/Grade/a86fa955b62542e4a2e9b88d9ee618d4)](https://app.codacy.com/gh/CommonGateway/HuwelijksplannerBundle/dashboard?utm_source=gh\&utm_medium=referral\&utm_content=\&utm_campaign=Badge_grade)

An symfony bundle for functionality about Dutch marriage request handling in municipalities

# Installatie

De huwelijksplanner backend codebase maakt gebruik van de common gateway als open source installatie framework. Dat betekent dat de huwelijksplanner library in haar meest essentiële vorm een plugin op dit Framework is. Meer informatie over de common gateway vind je hier.

De huwelijksplanner frontend codebase is een losse kubernetes container

# Veranderingen versus de huwelijksplanner 2020
in het vorige huwelijksplanner project is er voor gekozen om de huwelijksplanner volledig op te splitsen in register en per register een common ground component te ontwikkelen. De deze componenten moesten vervolgens los worden geïnstalleerd.

In de praktijk leide dit tot problemen, er was een groot aantal installaties nodig om de huwelijksplanner aan de praat te krijgen en het grote aantal code basis leidde tot onderhouds uitdagingen.   

Bij de nieuwe iteratie van de huwelijksplanner is er daarom voor gekozen om om de losse componenten als plugins op te zetten. Dat heeft twee primaire voordelen 

1. De onderliggende codebases zijn een stuk kleiner (en daarmee te onderhouden)
2. Deze verschillende plugins kunnen op één installatie van de Gateway worden gedraaid.

Optioneel kan de gemeente er dan nog steeds voor kiezen om de componenten in losse installaties te draaien, of te combineren tussen losse en een vaste combinatie. 

De bundels waar de huwelijksplanner op dit moment gebruik van maakt zijn
HuwelijksplannerBundle
https://github.com/CommonGateway/CalendarBundle (voorheen calender component)
https://github.com/CommonGateway/AssentBundle (voorheens assent component)
https://github.com/CommonGateway/ShopBundle (voorheen product, order, invoice en payment componenten)
https://github.com/CommonGateway/CommunicationBundle (voorheen email en sms componenten)
https://github.com/CommonGateway/TemplateBundle (voorheen template component)

Al deze bundles kunnen nog steeds als standalone component worden geinstaleerd (zie daarvoor de individuele installatie handleidingen) maar worden vanuit de huwelijksplanner standaard als extra plugins op dezelfde gateway geinstalleerd.


#Installeren van de huwelijksplanner

## Backend
Voor de backend installatie geld dat de standaard commongateway installatie handleiding gevolgd kan worden (als de gemeente nog geen commongateway installatie heeft) de handleiding treft u hier. Voor de opzet van de backend maakt het niet uit hoe u de gateway installeerd (bijvoorbeeld haven, kubernetes, linux of azure) of welke database optie u kiest (MySQL, Postgress, Oracle, MSsql). Het gateway framework handeld deze abstractie af.

Na het installeren van de Gateway logt u in en vind u onder “plugins” in het linker menu een overzicht van de reeds geinstalleerde plugins. Als de huwelijksplanner hier nog niet tussen staat kunt u rechtboven in op “Search” klikken en op “Huwelijksplanner” zoeken. Klik op de Card van huwelijksplanner en vervolgens op de knop installeren.

## Frontend
ToDo

## Testdata


#Bijwerken 
