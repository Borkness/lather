<img src="img/lather.svg" width="35%">

---

Lather is a simple to use but powerful SOAP client for PHP 7.2+ with the ability to utilize macros.

Through the use of classes querying SOAP API's becomes a breeze.

**Note:** Lather is still under development and therefore methods could be subject to change

## Installation

Lather requires that ext-soap is installed and that your PHP version is 7.2 or later.

#### Composer
```
composer install generalmoo\lather
```

## Basic Usage

>For more detailed instructions please visit the documentation located here

Firstly you must create a class named after the function method you wish to call defining the parameters that will be called along with the WSDL.

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
