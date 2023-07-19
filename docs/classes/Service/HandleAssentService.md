# CommonGateway\HuwelijksplannerBundle\Service\HandleAssentService

This service holds al the logic for approving or requesting a assent.

## Methods

| Name | Description |
|------|-------------|
|[\_\_construct](#handleassentservice__construct)||
|[checkSourceAuth](#handleassentservicechecksourceauth)|Check the auth of the given source.|
|[getStatus](#handleassentservicegetstatus)|Determines the status of the assent based on if the assent contains the bsn of the assentee.|
|[handleAssent](#handleassentservicehandleassent)|Handles the assent for the given person and sends an email or sms.|
|[sendEmail](#handleassentservicesendemail)|Sends an emails.|
|[sendSms](#handleassentservicesendsms)|Sends a sms.|

### HandleAssentService::\_\_construct

**Description**

```php
 __construct (void)
```

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

<hr />

### HandleAssentService::checkSourceAuth

**Description**

```php
public checkSourceAuth (\Source $source)
```

Check the auth of the given source.

**Parameters**

*   `(\Source) $source`
    : The given source to check the api key.

**Return Values**

`bool`

> If the api key is set or not.

<hr />

### HandleAssentService::getStatus

**Description**

```php
public getStatus (string $type, \ObjectEntity $person)
```

Determines the status of the assent based on if the assent contains the bsn of the assentee.

**Parameters**

*   `(string) $type`
    : The type of assent.
*   `(\ObjectEntity) $person`
    : The assentee of the assent.

**Return Values**

`string`

<hr />

### HandleAssentService::handleAssent

**Description**

```php
public handleAssent (\ObjectEntity $person, string $type, array $data, array $data, \ObjectEntity|null $assent)
```

Handles the assent for the given person and sends an email or sms.

**Parameters**

*   `(\ObjectEntity) $person`
    : The person to make/update an assent for.
*   `(string) $type`
    : The type of assent.
*   `(array) $data`
    : The data of the request.
*   `(array) $data`
    : The id of the property this assent is about.
*   `(\ObjectEntity|null) $assent`
    : The assent of the person

**Return Values**

`\ObjectEntity|null`

<hr />

### HandleAssentService::sendEmail

**Description**

```php
public sendEmail (object $emailAddresses, string $type, string $data)
```

Sends an emails.

**Parameters**

*   `(object) $emailAddresses`
    : The emailaddresses.
*   `(string) $type`
    : The type of the assent.
*   `(string) $data`
    : The data array of the request.

**Return Values**

`void`

<hr />

### HandleAssentService::sendSms

**Description**

```php
public sendSms (object $phoneNumbers, string $type)
```

Sends a sms.

**Parameters**

*   `(object) $phoneNumbers`
    : The phonenumbers.
*   `(string) $type`
    : The type of the assent.

**Return Values**

`void`

<hr />
