# TobyMaxham Laravel Properties

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tobymaxham/laravel-properties.svg?style=flat-square)](https://packagist.org/packages/tobymaxham/laravel-properties)
[![Total Downloads](https://img.shields.io/packagist/dt/tobymaxham/laravel-properties.svg?style=flat-square)](https://packagist.org/packages/tobymaxham/laravel-properties)

This package can be used to add some functionality to your Eloquent Models using properties.


## Installation

You can install the package via composer:

```bash
composer require tobymaxham/laravel-properties
```

## Usage

Your Eloquent Models should use the `TobyMaxham\LaravelProperties\Traits\UseProperties` trait.

The trait contains a few methods to help you handle JSON-Date in your Database Table Column.

Your models' migrations should have a field called `properties` to save the JSON-Data.

Here's an example of how to implement the trait:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\Traits\UseProperties;

class YourEloquentModel extends Model
{
    use UseProperties;
}
```

With its migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('your_eloquent_models', function (Blueprint $table) {
            $table->id();
            
            $table->json('properties');
            
            // ...
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('your_eloquent_models');
    }
};
```

### Use to store values in your Model

```php

$model = new EloquentModel();
$model->setProperty('key', 'value');
$model->save();

```

You can also use Laravel's "dot" notation to set a value:

```php
$model->setProperty('foo.bar', 'value');
```


### Receive a value from your Model 

```php
$model->getProperty('foo.bar'); // 'value'
```

You can also pass a default value:

```php
$model->getProperty('foo.baz', 'another Value'); // 'another Value'
```


## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.


## Security Vulnerabilities

If you've found a bug regarding security please mail git@maxham.de instead of using the issue tracker.


## Support me

[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/Z8Z4NZKU)


## Credits

- [TobyMaxham](https://github.com/TobyMaxham)
- [All Contributors](../../contributors)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
