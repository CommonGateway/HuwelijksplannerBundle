# CommonGateway\HuwelijksplannerBundle\Service\UpdateChecklistService  

This service holds al the logic for checksing data from the marriage request and updating the associated checklist.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#updatechecklistservice__construct)||
|[checkHuwelijk](#updatechecklistservicecheckhuwelijk)|Checks data from the marriage object and updates the associated checklist.|
|[checkHuwelijkCase](#updatechecklistservicecheckhuwelijkcase)|Checks the case of the huwelijk.|
|[checkHuwelijkMoment](#updatechecklistservicecheckhuwelijkmoment)|Checks the moment of the huwelijk.|
|[checkHuwelijkOfficer](#updatechecklistservicecheckhuwelijkofficer)|Checks the offeser of the huwelijk.|
|[checkHuwelijkOrder](#updatechecklistservicecheckhuwelijkorder)|Checks the order of the huwelijk.|
|[checkHuwelijkPartners](#updatechecklistservicecheckhuwelijkpartners)|Checks the partners of the huwelijk.|
|[checkHuwelijkProducts](#updatechecklistservicecheckhuwelijkproducts)|Checks the products of the huwelijk.|
|[checkHuwelijkWitnesses](#updatechecklistservicecheckhuwelijkwitnesses)|Checks the witnesses of the huwelijk.|




### UpdateChecklistService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### UpdateChecklistService::checkHuwelijk  

**Description**

```php
public checkHuwelijk (\ObjectEntity $huwelijk)
```

Checks data from the marriage object and updates the associated checklist. 

 

**Parameters**

* `(\ObjectEntity) $huwelijk`
: The huwelijk object  

**Return Values**

`\ObjectEntity`

> The huwelijk object with updated/created checklist


<hr />


### UpdateChecklistService::checkHuwelijkCase  

**Description**

```php
public checkHuwelijkCase (\ObjectEntity $huwelijk, array $checklist)
```

Checks the case of the huwelijk. 

 

**Parameters**

* `(\ObjectEntity) $huwelijk`
: The huwelijk object  
* `(array) $checklist`
: The checklist array  

**Return Values**

`\ObjectEntity`

> The huwelijk object with updated/created checklist


<hr />


### UpdateChecklistService::checkHuwelijkMoment  

**Description**

```php
public checkHuwelijkMoment (\ObjectEntity $huwelijk, array $checklist)
```

Checks the moment of the huwelijk. 

 

**Parameters**

* `(\ObjectEntity) $huwelijk`
: The huwelijk object  
* `(array) $checklist`
: The checklist array  

**Return Values**

`\ObjectEntity`

> The huwelijk object with updated/created checklist


<hr />


### UpdateChecklistService::checkHuwelijkOfficer  

**Description**

```php
public checkHuwelijkOfficer (\ObjectEntity $huwelijk, array $checklist)
```

Checks the offeser of the huwelijk. 

 

**Parameters**

* `(\ObjectEntity) $huwelijk`
: The huwelijk object  
* `(array) $checklist`
: The checklist array  

**Return Values**

`\ObjectEntity`

> The huwelijk object with updated/created checklist


<hr />


### UpdateChecklistService::checkHuwelijkOrder  

**Description**

```php
public checkHuwelijkOrder (\ObjectEntity $huwelijk, array $checklist)
```

Checks the order of the huwelijk. 

 

**Parameters**

* `(\ObjectEntity) $huwelijk`
: The huwelijk object  
* `(array) $checklist`
: The checklist array  

**Return Values**

`\ObjectEntity`

> The huwelijk object with updated/created checklist


<hr />


### UpdateChecklistService::checkHuwelijkPartners  

**Description**

```php
public checkHuwelijkPartners (\ObjectEntity $huwelijk, array $checklist)
```

Checks the partners of the huwelijk. 

 

**Parameters**

* `(\ObjectEntity) $huwelijk`
: The huwelijk object  
* `(array) $checklist`
: The checklist array  

**Return Values**

`\ObjectEntity`

> The huwelijk object with updated/created checklist


<hr />


### UpdateChecklistService::checkHuwelijkProducts  

**Description**

```php
public checkHuwelijkProducts (\ObjectEntity $huwelijk, array $checklist)
```

Checks the products of the huwelijk. 

 

**Parameters**

* `(\ObjectEntity) $huwelijk`
: The huwelijk object  
* `(array) $checklist`
: The checklist array  

**Return Values**

`\ObjectEntity`

> The huwelijk object with updated/created checklist


<hr />


### UpdateChecklistService::checkHuwelijkWitnesses  

**Description**

```php
public checkHuwelijkWitnesses (\ObjectEntity $huwelijk, array $checklist)
```

Checks the witnesses of the huwelijk. 

 

**Parameters**

* `(\ObjectEntity) $huwelijk`
: The huwelijk object  
* `(array) $checklist`
: The checklist array  

**Return Values**

`\ObjectEntity`

> The huwelijk object with updated/created checklist


<hr />

