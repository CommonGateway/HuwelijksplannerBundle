# CommonGateway\HuwelijksplannerBundle\Service\PaymentService  

This service holds al the logic for mollie payments.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#paymentservice__construct)||
|[calculatePrice](#paymentservicecalculateprice)|Calculates total price with given prices and currency.|
|[checkSourceAuth](#paymentservicechecksourceauth)|Check the auth of the given source.|
|[createMolliePayment](#paymentservicecreatemolliepayment)|Creates a payment object.|
|[createPayment](#paymentservicecreatepayment)|Creates a payment object.|
|[createPaymentHandler](#paymentservicecreatepaymenthandler)|Creates payment for given marriage.|
|[getProductArrayPrices](#paymentservicegetproductarrayprices)|Get product prices from this marriage.|
|[getSDGProductPrices](#paymentservicegetsdgproductprices)|Get product prices from this marriage.|




### PaymentService::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### PaymentService::calculatePrice  

**Description**

```php
public calculatePrice (array $prices, string|null $currency)
```

Calculates total price with given prices and currency. 

 

**Parameters**

* `(array) $prices`
: Array of prices to accumulate.  
* `(string|null) $currency`
: ISO 4271 currency.  

**Return Values**

`string`

> Total price after acummulation.


<hr />


### PaymentService::checkSourceAuth  

**Description**

```php
public checkSourceAuth (\Source $source)
```

Check the auth of the given source. 

 

**Parameters**

* `(\Source) $source`
: The given source to check the api key.  

**Return Values**

`bool`

> If the api key is set or not.


<hr />


### PaymentService::createMolliePayment  

**Description**

```php
public createMolliePayment (array $paymentArray)
```

Creates a payment object. 

The required fields in the paymentArray are:  
The amount object with currency and value.  
The string descrtiption.  
The string redirectUrl were mollie has to redirect to after the payment.  
The method array with the payment methods. 

**Parameters**

* `(array) $paymentArray`
: The body for the payment request.  

**Return Values**

`array|null`

> Syncrhonization object or a error repsonse or null.


<hr />


### PaymentService::createPayment  

**Description**

```php
public createPayment (void)
```

Creates a payment object. 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`array|null`

> Payment object as array or null.


<hr />


### PaymentService::createPaymentHandler  

**Description**

```php
public createPaymentHandler (?array $data, ?array $configuration)
```

Creates payment for given marriage. 

 

**Parameters**

* `(?array) $data`
: Data this service might need from a Action.  
* `(?array) $configuration`
: Configuraiton this service might need from a Action.  

**Return Values**

`array`

> Response array that will be returned to RequestService.


<hr />


### PaymentService::getProductArrayPrices  

**Description**

```php
public getProductArrayPrices (array $products)
```

Get product prices from this marriage. 

 

**Parameters**

* `(array) $products`
: The products array from the marriage.  

**Return Values**

`array`

> $productPrices Array of all product prices.


<hr />


### PaymentService::getSDGProductPrices  

**Description**

```php
public getSDGProductPrices (array $huwelijk)
```

Get product prices from this marriage. 

 

**Parameters**

* `(array) $huwelijk`
: Huwelijk object as array.  

**Return Values**

`array`

> $productPrices Array of all product prices.


<hr />

