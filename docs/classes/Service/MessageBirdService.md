# CommonGateway\HuwelijksplannerBundle\Service\MessageBirdService  

This service holds all the logic for sending a message with messagebird.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#messagebirdservice__construct)||
|[importMessage](#messagebirdserviceimportmessage)||
|[messageBirdHandler](#messagebirdservicemessagebirdhandler)|Sends message via messageBird|
|[sendMessage](#messagebirdservicesendmessage)|Handles sending a message with messagebird.|




### MessageBirdService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### MessageBirdService::importMessage  

**Description**

```php
 importMessage (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### MessageBirdService::messageBirdHandler  

**Description**

```php
public messageBirdHandler (?array $data, ?array $configuration)
```

Sends message via messageBird 

 

**Parameters**

* `(?array) $data`
: Data this service might need from a Action.  
* `(?array) $configuration`
: Configuraiton this service might need from a Action.  

**Return Values**

`array`

> Response array that will be returned to RequestService.


<hr />


### MessageBirdService::sendMessage  

**Description**

```php
public sendMessage (string $recipients, string $body)
```

Handles sending a message with messagebird. 

 

**Parameters**

* `(string) $recipients`
* `(string) $body`

**Return Values**

`bool`




<hr />

