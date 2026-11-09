# From 1.x to 2.0

Version 2.0 contains breaking changes. Most apps only need the checklist, the sections below explain every change.

## Upgrade with an AI agent

The package ships step by step upgrade instructions for AI agents as [Agent Skill](resources/boost/skills/laravel-properties-upgrade/SKILL.md).
The agent searches your code, asks you before decisions that change behavior, updates the package and the code, runs your tests and does not commit.

Paste this prompt into Claude Code, Cursor, Copilot or a similar agent:

```text
Upgrade tobymaxham/laravel-properties from 1.x to 2.0. Follow the instructions in
https://raw.githubusercontent.com/TobyMaxham/laravel-properties/main/resources/boost/skills/laravel-properties-upgrade/SKILL.md
```

With [Laravel Boost](https://laravel.com/docs/boost), the skill is installed with `php artisan boost:install` / `boost:update` once the package is on 2.x. Then ask your agent to "upgrade laravel-properties".

Always review the changes before you deploy them.

## Checklist

1. Update to PHP 8.3+ and Laravel 12 or 13.
2. Run `composer require tobymaxham/laravel-properties:^2.0`.
3. Replace `use TobyMaxham\LaravelProperties\Traits\UseProperties;` with `use TobyMaxham\LaravelProperties\HasProperties;` (optional, the old trait still works).
4. **Check every `getProperty()` call with a default value** (see [Default values](#default-values)).
5. Replace `$propertiesField` with `$propertiesColumn` and `emptyProperties()` with `clearProperties()` (optional, both still work).
6. Remove the `'properties' => 'array'` cast from your models (optional, it is detected).
7. Check your models for [new methods](#new-methods) that collide with your own methods.
8. Make the column nullable (recommended, see [Column](#column)).

## Requirements

| | 1.x | 2.0 |
|---|---|---|
| PHP | 7.4+ (needed 8.0+ in practice) | **8.3+** |
| Laravel | 8 – 12 | **12, 13** |

## Default values

**This is the most important change.** In 1.x the `$default` of `getProperty()` was ignored, missing properties always returned `null`:

```php
$model->getProperty('missing', 'fallback');
// 1.x: null
// 2.0: 'fallback'
```

Code that passed a default but relied on `null` behaves differently now. Search for `getProperty(` calls with two arguments and check each of them.

## Trait

```php
// 1.x
use TobyMaxham\LaravelProperties\Traits\UseProperties;

// 2.0
use TobyMaxham\LaravelProperties\HasProperties;
```

`UseProperties` is an alias of `HasProperties` and will be removed in 3.0. The optional interface `TobyMaxham\LaravelProperties\Contracts\HasProperties` lets other code detect models with properties.

## Column

The column name is configured with `$propertiesColumn` (or `PROPERTIES_COLUMN`, `propertiesColumn()`). In 1.x redeclaring `$propertiesField` was a fatal error, so only `properties` worked. `$propertiesField` is still read, but deprecated.

```php
protected string $propertiesColumn = 'settings';
```

The column should be nullable. In 1.x the readme used `$table->json('properties')`, which fails on MySQL when a model is created without properties:

```php
Schema::table('users', function (Blueprint $table) {
    $table->json('properties')->nullable()->change();
});
```

## Cast

The trait adds its own cast (`AsProperties`) for the column. A Laravel cast of the column is detected and keeps its type, so you can remove it:

| Your cast in 1.x                          | Type in 2.0                      |
|-------------------------------------------|----------------------------------|
| none                                      | `array`, 1.x did not work without cast |
| `array`, `json`                           | `array`                          |
| `object`, `AsArrayObject`                 | `ArrayObject` (instead of `stdClass` for `object`) |
| `collection`, `AsCollection`              | `Collection`                     |
| `encrypted:array`, `encrypted:object`, ... | encrypted, same type as above   |
| a custom cast class                       | kept as it is                    |

Configure the type yourself with `PROPERTIES_CAST` (see the [readme](README.md#type-of-the-attribute)).

## Stored JSON

- Empty properties are stored as `{}` instead of `[]`.
- Unicode and slashes are not escaped anymore (`"Jürgen"` instead of `"Jürgen"`). The data is the same, and the model does not become dirty because of the different format.

## Methods

| 1.x                                   | 2.0                                                         |
|---------------------------------------|-------------------------------------------------------------|
| `setProperty()` returns `void`        | returns the model, for chaining                             |
| `emptyProperties()` returns `?array`  | returns `array`, deprecated, use `clearProperties()`        |

## New methods

The trait adds these methods and scopes to your models. Rename your own methods if they have the same name:

- `props()`, `propertiesColumn()`, `propertiesCast()`, `propertyDefaults()`, `propertyCasts()`, `serializePropertyDefaults()`, `withPropertyDefaults()`, `withoutPropertyDefaults()`, `propertiesForSerialization()`
- `getProperties()`, `getStoredProperties()`, `hasProperty()`, `setProperties()`, `replaceProperties()`, `forgetProperty()`, `clearProperties()`, `pushProperty()`, `pullProperty()`, `incrementProperty()`, `decrementProperty()`, `toggleProperty()`, `onlyProperties()`, `exceptProperties()`
- scopes `whereProperty()`, `orWhereProperty()`, `whereProperties()`, `wherePropertyIn()`, `wherePropertyNotIn()`, `wherePropertyNull()`, `wherePropertyNotNull()`, `wherePropertyBetween()`, `wherePropertyNotBetween()`, `whereHasProperty()`, `whereDoesntHaveProperty()`, `wherePropertyContains()`, `wherePropertyDoesntContain()`, `wherePropertyLength()`, `orderByProperty()`, `orderByPropertyDesc()`
- the protected property `$serializePropertyDefaultsOverride`

## Exceptions

All exceptions extend `TobyMaxham\LaravelProperties\Exceptions\PropertiesException`, a `RuntimeException`. `catch (\Exception $e)` still works.

- `InvalidAttributeTypeException` is thrown when the column does not contain a JSON object or array.
- `InvalidObjectTypeException` is not thrown anymore (the trait only works on Eloquent models) and will be removed in 3.0.
- New: `InvalidPropertyValueException` and `CannotQueryEncryptedPropertyException`.
