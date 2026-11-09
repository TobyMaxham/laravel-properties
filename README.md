[![Laravel Properties][laravel-properties-image]][laravel-properties-edit-link]

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tobymaxham/laravel-properties.svg?style=flat-square)](https://packagist.org/packages/tobymaxham/laravel-properties)
[![Tests](https://img.shields.io/github/actions/workflow/status/TobyMaxham/laravel-properties/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/TobyMaxham/laravel-properties/actions/workflows/tests.yml)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/tobymaxham/laravel-properties.svg?style=flat-square)](https://packagist.org/packages/tobymaxham/laravel-properties)
[![Support me on Patreon](https://img.shields.io/endpoint.svg?url=https%3A%2F%2Fshieldsio-patreon.vercel.app%2Fapi%3Fusername%3DTobymaxham%26type%3Dpatrons&style=flat)](https://patreon.com/Tobymaxham)


Flexible properties for your Eloquent models, stored in a single JSON column.
With defaults, casts, enums, encryption, query scopes and migration helpers.

```php
class User extends Model
{
    use HasProperties;

    protected array $propertyDefaults = ['theme' => 'light', 'newsletter' => false];

    protected function propertyCasts(): array
    {
        return ['newsletter' => 'bool', 'plan' => Plan::class, 'api_token' => 'encrypted'];
    }
}

$user->getProperty('theme');                   // 'light' (default, nothing stored)
$user->setProperty('plan', 'pro')->save();     // stored as "pro"
$user->getProperty('plan');                    // Plan::Pro

User::whereProperty('plan', Plan::Pro)->get();
User::whereProperty('newsletter', false)->get(); // also finds users without the property
```


## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Reading and writing](#reading-and-writing)
- [The `props()` object](#the-props-object)
- [Configuration](#configuration)
- [Defaults](#defaults)
- [Casts](#casts)
- [Encryption](#encryption)
- [Queries](#queries)
- [Migrations and indexes](#migrations-and-indexes)
- [Database support](#database-support)
- [When to use properties](#when-to-use-properties)
- [Exceptions](#exceptions)


## Requirements

- PHP 8.3 or higher
- Laravel 12 or 13
- MySQL 8.0.17+, MariaDB 10.5+, PostgreSQL, SQLite or SQL Server (see [Database support](#database-support))

Use version `1.x` for older PHP or Laravel versions.

**Upgrading from 1.x?** Please read the [upgrade guide](UPGRADING.md), or let your [AI agent do the upgrade](UPGRADING.md#upgrade-with-an-ai-agent).


## Installation

```sh
composer require tobymaxham/laravel-properties
```

The service provider is registered automatically.

Add a nullable JSON column to your table:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->properties(); // json('properties')->nullable()
    $table->timestamps();
});
```

For an existing table, create the migration with Artisan:

```sh
php artisan make:properties-migration users
```

Then add the trait to your model:

```php
use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\HasProperties;

class User extends Model
{
    use HasProperties;
}
```

The trait adds the cast for the column itself, you do not need `'properties' => 'array'`.

Implementing `TobyMaxham\LaravelProperties\Contracts\HasProperties` is optional. It lets other code detect models with properties.


## Reading and writing

Keys use Laravel's "dot" notation. All methods change the model only, call `save()` to store the changes.

```php
$user->setProperty('ui.theme', 'dark');
$user->setProperties(['ui.size' => 'lg', 'newsletter' => true]); // keeps the other properties
$user->save();

$user->getProperty('ui.theme');            // 'dark'
$user->getProperty('ui');                  // ['theme' => 'dark', 'size' => 'lg']
$user->getProperty('missing', 'fallback'); // 'fallback' (also as closure: fn () => ...)
$user->hasProperty('ui.size');             // true

$user->getProperties();                    // all properties, merged with the defaults
$user->getStoredProperties();              // only what is stored, without defaults and casts
$user->onlyProperties(['ui.theme', 'newsletter']);
$user->exceptProperties('ui');
```

```php
$user->forgetProperty('ui.size');          // or an array of keys
$user->replaceProperties(['theme' => 'dark']);
$user->clearProperties();                  // stored as {}

$user->pushProperty('tags', 'laravel', 'php');
$user->pullProperty('legacy');             // get and remove
$user->incrementProperty('logins');        // decrementProperty('credits', 5)
$user->toggleProperty('newsletter');
```

All setters return the model, so you can chain them: `$user->setProperty('a', 1)->forgetProperty('b')->save()`.

> A key with a dot (`'a.b'` as real key) can be read, but not written with the methods above. Avoid dots in keys.


## The `props()` object

`props()` gives you fluent access to the same properties. It is handy in views, resources and forms.

```php
$props = $user->props();

$props->theme;                  // like getProperty('theme')
$props->theme = 'dark';         // like setProperty('theme', 'dark')
$props['ui.size'];              // array access with "dot" notation
unset($props['legacy']);
isset($props->theme);

$props->set('theme', 'dark')->set(['ui.size' => 'lg'])->increment('logins');
$props->get('ui.size', 'md');
$props->all();                  // with defaults and casts
$props->stored();               // without defaults and casts
$props->collect();              // as Collection
count($props);
foreach ($props as $key => $value) { /* ... */ }
json_encode($props);
```

`props()` has the same methods as the model: `get`, `set`, `has`, `forget`, `all`, `stored`, `defaults`, `only`, `except`, `merge`, `replace`, `clear`, `push`, `pull`, `increment`, `decrement`, `toggle`.


## Configuration

Everything is optional. Use a method, a constant or a property, whatever you prefer (a method wins over a constant, a constant over a property).

| What                        | Method                        | Constant                      | Property                     | Default        |
|-----------------------------|-------------------------------|-------------------------------|------------------------------|----------------|
| Column                      | `propertiesColumn()`          | `PROPERTIES_COLUMN`           | `$propertiesColumn`          | `'properties'` |
| Type of `$model->properties`| `propertiesCast()`            | `PROPERTIES_CAST`             | `$propertiesCast`            | `Array`        |
| [Defaults](#defaults)       | `propertyDefaults()`          | `PROPERTY_DEFAULTS`           | `$propertyDefaults`          | `[]`           |
| [Casts](#casts)             | `propertyCasts()`             | `PROPERTY_CASTS`              | `$propertyCasts`             | `[]`           |
| Defaults in `toArray()`     | `serializePropertyDefaults()` | `SERIALIZE_PROPERTY_DEFAULTS` | `$serializePropertyDefaults` | `false`        |

```php
use TobyMaxham\LaravelProperties\Enums\PropertiesCast;

class User extends Model
{
    use HasProperties;

    public const string PROPERTIES_COLUMN = 'settings';

    public const PropertiesCast PROPERTIES_CAST = PropertiesCast::Object;
}
```

### Type of the attribute

`$model->properties` (the attribute itself) returns the stored values, without defaults and property casts:

| `PropertiesCast`                          | `$model->properties` is                       | Like Laravel's                 |
|-------------------------------------------|-----------------------------------------------|--------------------------------|
| `Array` (default)                         | `array`                                       | `array`                        |
| `Object`                                  | `ArrayObject`, `$model->properties['a'] = 1` works | `AsArrayObject`           |
| `Collection`                              | `Collection`                                  | `AsCollection`                 |
| `EncryptedArray`, `EncryptedObject`, `EncryptedCollection` | as above, the whole column is [encrypted](#encryption) | `encrypted:array`, ... |

An existing Laravel cast of the column (`array`, `json`, `AsArrayObject`, `encrypted:array`, ...) is detected and keeps its type. A custom cast class is kept as it is.


## Defaults

Defaults are only used when reading. They are never stored, so the model does not become dirty, and a changed default applies to all existing rows at once.

```php
class User extends Model
{
    use HasProperties;

    protected array $propertyDefaults = [
        'theme' => 'light',
        'ui'    => ['size' => 'md', 'density' => 'normal'],
        'roles' => ['user'],
    ];
}

$user->setProperty('ui.size', 'lg');

$user->getProperty('theme');   // 'light'
$user->getProperty('ui');      // ['size' => 'lg', 'density' => 'normal']
$user->hasProperty('theme');   // true
$user->getStoredProperties();  // ['ui' => ['size' => 'lg']]
```

- Nested arrays are merged, lists (like `roles`) are replaced.
- A stored `null` stays `null`. `forgetProperty()` brings the default back.
- [Queries](#queries) know the defaults as well.

By default, `toArray()` and JSON only contain the stored properties. Let the model or a single call decide:

```php
public const bool SERIALIZE_PROPERTY_DEFAULTS = true;  // always with defaults

$user->withPropertyDefaults()->toArray();              // this instance with defaults
$user->withoutPropertyDefaults()->toJson();            // this instance without defaults
```


## Casts

Cast single properties like Laravel's attribute casts. Keys may use "dot" notation.

```php
use TobyMaxham\LaravelProperties\PropertyCasts\EnumCast;

protected function propertyCasts(): array
{
    return [
        'logins'          => 'int',
        'price'           => 'decimal:2',
        'newsletter'      => 'bool',
        'birthday'        => 'date',
        'starts_at'       => 'datetime:d.m.Y H:i',
        'plan'            => Plan::class,                    // backed enum
        'mood'            => EnumCast::lenient(Mood::class), // unknown values become null
        'address.country' => Country::class,                 // nested key
        'amount'          => MoneyCast::class.':EUR',        // custom cast with arguments
    ];
}
```

| Cast                                                                  | Reading returns                  | Stored as                         |
|-----------------------------------------------------------------------|----------------------------------|-----------------------------------|
| `int` / `integer`, `float` / `double` / `real`                        | `int`, `float`                   | number                            |
| `decimal:<digits>`                                                    | `string` with the digits         | string, e.g. `"12.50"`            |
| `bool` / `boolean`                                                    | `bool` (`'1'`, `'yes'`, `'on'` → `true`) | boolean                   |
| `string`                                                              | `string`                         | string                            |
| `array` / `json`, `collection`                                        | `array`, `Collection`            | JSON array / object               |
| `date`, `datetime`, `immutable_date`, `immutable_datetime`, with optional `:<format>` | Carbon (respects `Date::use()`) | `Y-m-d`, `Y-m-d H:i:s` or the format |
| a backed enum class                                                   | the enum                         | the value                         |
| `encrypted`, `encrypted:<cast>`                                       | see [Encryption](#encryption)    | encrypted string                  |

**The rules:**

- **Writing is strict:** `setProperty('logins', 'abc')` throws an `InvalidPropertyValueException`. `'5'` becomes `5`.
- **Reading never throws for simple types** and converts like Laravel, so old data stays readable. Enums and dates throw for invalid values.
- **Only the key you read is cast:** an invalid enum in `plan` does not break `getProperty('theme')`. `getProperties()` casts all properties.
- **Only new values are cast when writing:** existing data is not rewritten.
- The `$default` of `getProperty($key, $default)` is returned as it is. Defaults of the model are cast, so enums work as defaults.
- Dates are stored without timezone conversion (like Laravel's date columns), microseconds are lost.
- Enums and dates **without** a cast are stored as JSON (`'active'`, `'2024-01-01T00:00:00.000000Z'`) and read back as these strings.

**Enums** are strict by default: an unknown value throws when reading or writing. Use `EnumCast::lenient(Mood::class)` to get `null` instead. Int backed enums also accept `'2'` from forms.

**Custom casts** implement `TobyMaxham\LaravelProperties\Contracts\PropertyCast`:

```php
use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\Contracts\PropertyCast;

class MoneyCast implements PropertyCast
{
    public function __construct(private string $currency = 'EUR') {}

    public function get(mixed $value, string $key, Model $model): ?Money
    {
        return null === $value ? null : new Money($value, $this->currency);
    }

    public function set(mixed $value, string $key, Model $model): ?int
    {
        return match (true) {
            null === $value          => null,
            $value instanceof Money  => $value->cents,
            default                  => (int) round((float) $value * 100),
        };
    }
}
```

Use it as `MoneyCast::class`, `MoneyCast::class.':USD'` (arguments separated by `,`) or `new MoneyCast('USD')` inside `propertyCasts()`.


## Encryption

**Single properties** are encrypted with the `encrypted` cast. Add a cast for the decrypted value after the colon:

```php
protected function propertyCasts(): array
{
    return [
        'api_token'   => 'encrypted',
        'pin'         => 'encrypted:int',
        'expires_at'  => 'encrypted:datetime',
        'level'       => 'encrypted:'.Level::class,
        'oauth.token' => 'encrypted',
    ];
}
```

- The value is encrypted as JSON, so its type survives (`int`, `array`, ...). `null` is not encrypted.
- The encrypter of the model is used (`Model::encryptUsing()`, default `Crypt`). Key rotation with `APP_PREVIOUS_KEYS` works.
- Setting the same value again keeps the old encryption, so the model does not become dirty.
- Encrypted properties are **hidden** in `toArray()` and JSON.
- Encrypted properties can not have [defaults](#defaults): they would be stored in plain text in your code.

**The whole column** is encrypted with an encrypted [type](#type-of-the-attribute). The column must be `text`:

```php
$table->properties(encrypted: true);

public const PropertiesCast PROPERTIES_CAST = PropertiesCast::EncryptedArray;
```

Like Laravel's `encrypted:array`, `toArray()` contains the decrypted values of an encrypted column.

**Encrypted values can not be queried:** every encryption of the same value is different. The [query scopes](#queries) throw a `CannotQueryEncryptedPropertyException`. Only `whereHasProperty()` works for single encrypted properties. Check it yourself with `$model->props()->isQueryable('api_token')`.


## Queries

```php
User::whereProperty('theme', 'dark')->get();
User::whereProperty('logins', '>', 10)->orWhereProperty('plan', Plan::Pro)->get();
User::whereProperties(['theme' => 'dark', 'newsletter' => true])->get();

User::wherePropertyIn('plan', [Plan::Pro, Plan::Team])->get();
User::wherePropertyNotIn('theme', ['dark']);
User::wherePropertyNull('deleted_reason');
User::wherePropertyNotNull('ui.theme');
User::wherePropertyBetween('logins', [10, 100]);
User::wherePropertyNotBetween('birthday', ['2000-01-01', '2009-12-31']);

User::whereHasProperty('api_token');                 // the property exists (or has a default)
User::whereDoesntHaveProperty('legacy');
User::wherePropertyContains('roles', 'admin');       // or several: ['admin', 'editor']
User::wherePropertyDoesntContain('roles', 'banned');
User::wherePropertyLength('tags', '>', 2);           // or wherePropertyLength('tags', 3)

User::orderByProperty('logins', 'desc')->get();      // or orderByPropertyDesc('logins')
```

The scopes work on relations and inside `whereHas()`, and the column is qualified (`users.properties`), so joins work.

**What the scopes do for you:**

- **Casts:** values are converted with the [property casts](#casts): `Plan::Pro` → `'pro'`, `'1'` → `true` for `bool`, Carbon → `'2024-01-01'` for `date`.
- **Defaults:** if the default matches the condition, rows without the property match too. With the default `newsletter = false`, `whereProperty('newsletter', false)` also finds users that never set it.
- **Numbers** are compared and sorted as numbers on every database (otherwise `'10'` would be smaller than `'9'`).
- **Encryption:** encrypted properties throw a `CannotQueryEncryptedPropertyException`.

**Good to know:**

- SQL rules for `null` apply: `whereProperty('theme', '!=', 'light')` does not find rows where `theme` is `null`. Use `wherePropertyNull()` for those.
- Dates only compare correctly in sortable formats like `Y-m-d` and `Y-m-d H:i:s`, not with `d.m.Y`.
- Compare lists with `wherePropertyContains()`, not with `=`.
- `whereHasProperty()` works like `hasProperty()`: a property with a default always exists.


## Migrations and indexes

### Columns

```php
$table->properties();                         // json, nullable
$table->properties('settings', jsonb: true);  // jsonb on PostgreSQL
$table->properties('secrets', encrypted: true); // text, for encrypted columns
$table->dropProperties('settings');
```

```sh
php artisan make:properties-migration users
php artisan make:properties-migration users --column=settings --jsonb --index=ui.theme
php artisan make:properties-migration users --encrypted
```

### Indexes

Add an index for properties you query often:

```php
$table->propertyIndex('ui.theme');                       // for whereProperty('ui.theme', ...)
$table->propertyIndex('plan', 'settings', name: 'plan_index');
$table->dropPropertyIndex('ui.theme');
```

The index uses exactly the expression of the query scopes, so the database uses it:

| Database   | Index                                                                                                 |
|------------|-------------------------------------------------------------------------------------------------------|
| MySQL      | a virtual column `properties_ui_theme` with an index, used automatically by `whereProperty()`         |
| PostgreSQL | an expression index                                                                                   |
| SQLite     | an expression index                                                                                   |
| MariaDB    | a virtual column with an index. MariaDB does not use it automatically, query the column: `where('properties_ui_theme', 'dark')` |
| SQL Server | not supported                                                                                         |

Indexes help with `=`, `in` and string comparisons. Numeric comparisons (`>`, `<`, `orderByProperty()` of numbers) do not use them.

### Changing existing data

Change the properties of all rows in a migration. It runs in chunks, without model events and without touching `updated_at`, and returns the number of changed rows:

```php
use TobyMaxham\LaravelProperties\Properties;

Properties::table('users')                       // plain JSON, without model
    ->rename('theme', 'ui.theme')
    ->forget(['legacy_flag', 'old.key'])
    ->set('newsletter', false, onlyIfMissing: true)
    ->set('label', fn (array $properties, object $row) => "user-{$row->id}")
    ->transform('price', fn ($cents) => $cents / 100)
    ->where(fn ($query) => $query->where('active', true))
    ->run();

Properties::for(User::class)                     // with the casts and encryption of the model
    ->set('plan', Plan::Free, onlyIfMissing: true)
    ->set('api_token', fn () => Str::random(40))
    ->run();
```

Move columns into properties and back:

```php
Properties::table('users')->moveIn(['newsletter', 'locale' => 'ui.locale'])->run();
Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['newsletter', 'locale']));

Properties::table('users')->moveOut(['ui.locale' => 'locale'])->run(); // the column must exist
```

- `Properties::for()` includes soft deleted rows and uses the casts for `set()`, `transform()` and `moveIn()`.
- `rename()` and `moveOut()` move the stored values as they are (for encrypted properties the encrypted value).
- `moveIn()` does not copy `null`, `moveOut()` writes `null` for rows without the property.


## Database support

The package is tested on every change with:

| Database   | Tested version | JSON column           | Notes                                                   |
|------------|----------------|-----------------------|---------------------------------------------------------|
| SQLite     | 3              | `text`                | invalid JSON is only rejected by the package            |
| MySQL      | 8.4            | `json`                | reorders the keys of objects, the data stays the same   |
| MariaDB    | 11             | `longtext` with check | indexes need a direct query, see [Indexes](#indexes)    |
| PostgreSQL | 17             | `json` or `jsonb`     |                                                         |
| SQL Server | (generated SQL)| `nvarchar(max)`       | no property indexes                                     |


## When to use properties

**Good for:** settings and preferences, feature flags, optional data that only some rows have, data with a structure that changes often, data from external systems.

**Use real columns for:** foreign keys, data you filter, sort or join by in large tables all the time, and data that needs constraints (unique, not null, foreign keys). With [`moveOut()`](#changing-existing-data) you can turn a property into a column later.


## Exceptions

All exceptions extend `TobyMaxham\LaravelProperties\Exceptions\PropertiesException`:

| Exception                                | When                                                                   |
|------------------------------------------|------------------------------------------------------------------------|
| `InvalidPropertyValueException`          | a value does not fit its cast, e.g. `'abc'` for `int`, unknown enum value, `incrementProperty()` on a string |
| `InvalidAttributeTypeException`          | the column does not contain a JSON object or array                     |
| `CannotQueryEncryptedPropertyException`  | a query uses an encrypted property or an encrypted column               |

An invalid configuration (unknown cast, empty column name, default for an encrypted property, ...) throws an `InvalidArgumentException`.


## Testing

```bash
composer test
composer test-coverage
composer analyse
```

Run the tests against another database:

```bash
DB_CONNECTION=mysql DB_PORT=3306 DB_PASSWORD=secret composer test
```


## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.


## Security Vulnerabilities

If you've found a bug regarding security please mail [git@maxham.net](mailto:git@maxham.net) instead of using the issue tracker.


## Support me

[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/Z8Z4NZKU)<br>
[![Support me on Patreon](https://img.shields.io/endpoint.svg?url=https%3A%2F%2Fshieldsio-patreon.vercel.app%2Fapi%3Fusername%3DTobymaxham%26type%3Dpatrons&style=flat)](https://patreon.com/Tobymaxham)


## Credits

- [TobyMaxham](https://github.com/TobyMaxham)
- [All Contributors](../../contributors)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


[laravel-properties-image]: https://socialify.git.ci/TobyMaxham/laravel-properties/image?description=1&font=Raleway&issues=1&language=1&owner=1&pattern=Charlie%20Brown&pulls=1&stargazers=1&theme=Light
[laravel-properties-edit-link]: https://socialify.git.ci/TobyMaxham/laravel-properties?description=1&font=Raleway&issues=1&language=1&owner=1&pattern=Charlie%20Brown&pulls=1&stargazers=1&theme=Light
