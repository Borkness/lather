<img src="img/lather.svg" width="35%">

---

Lather is a simple to use SOAP client for PHP 7.2+.

Through the use of Lather querying SOAP API's becomes a breeze.

**Note:** Lather is still under development and therefore methods could be subject to change

## Installation

Lather requires that ext-soap is installed and that your PHP version is 7.2 or later.

#### Composer
```
composer require borkness\lather
```

## Basic Usage

Firstly you must create a class, by default the soap function called will be the name of the class, defining the parameters that will be called along with the WSDL.

```php
<?php

namespace App;

use Lather\Lather;

class Add extends Lather
{
    protected $parameters = [
        'number1',
        'number2',
    ];

    protected $wsdl = 'calculator.wsdl';
}
```

You are now ready to call the SOAP service.

```php
<?php

require_once('vendor/autoload.php');

$add = new App\Add();

$add->call(['number1' => 5, 'number2' => 5]);

print_r($add->all());
```

**Response:**
```
Array
(
    [AddResult] => 10
)
```

## Joins
You can use the join functionality in Lather to obtain data over multiple SOAP operations that will be returned as one response array.

Specify an array of `$joins` using the key as the class name and a value of the response with the ID for join call formatted as a comma seperated string.

### Example:
```php
<?php

namespace App;

use Lather\Lather;

class GetCountryISOCode extends Lather
{
    protected $wsdl = 'CountryInfoService.wsdl';

    protected $params = [
        'sCountryName' => 'string',
    ];

    protected $functionName = 'CountryISOCode';

    /*
       Joins:
       Format of Class name as key with ResultArray and Join Id as comma value
    */
    protected $joins = [
        CapitalCity::class => 'CountryISOCodeResult,sCountryISOCode',
        CountryCurrency::class => 'CountryISOCodeResult,sCountryISOCode',
    ];
}

```

```php
<?php

require_once('vendor/autoload.php');

$countryCode = new App\GetCountryISOCode();

$countryCode->sCountryName = 'Denmark';

$countryCode->call();

print_r($countryCode->all());
```

**Response:**
```
Array
(
    [CountryISOCodeResult] => DK
    [CapitalCityResult] => Copenhagen
    [CountryCurrencyResult] => Array
        (
            [sISOCode] => DKK
            [sName] => Kroner
        )

)
```