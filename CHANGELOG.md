# Changelog

All notable changes to `tobymaxham/laravel-properties` will be documented in this file

## 2.0.0 - 2026-11-09

Major release with breaking changes. Please read the [upgrade guide](UPGRADING.md).

> **Upgrade with an AI agent:** paste this prompt into Claude Code, Cursor, Copilot or a similar agent:
>
> ```text
> Upgrade tobymaxham/laravel-properties from 1.x to 2.0. Follow the instructions in
> https://raw.githubusercontent.com/TobyMaxham/laravel-properties/main/resources/boost/skills/laravel-properties-upgrade/SKILL.md
> ```

### Breaking Changes

- Requires PHP 8.3+ and Laravel 12 or 13 (dropped PHP 7.4 – 8.2 and Laravel 8 – 11)
- New trait `TobyMaxham\LaravelProperties\HasProperties`. `Traits\UseProperties` still works, but is deprecated
- `getProperty($key, $default)` returns `$default` for missing properties (1.x always returned `null`)
- The trait adds its own cast (`AsProperties`) for the column. Existing Laravel casts like `'properties' => 'array'` are detected and keep their type
- Empty properties are stored as `{}` instead of `[]`
- `emptyProperties()` returns an `array` instead of `?array` and is deprecated, use `clearProperties()`
- `setProperty()` returns the model instead of `void`
- All exceptions extend the new `PropertiesException` (a `RuntimeException`). `InvalidObjectTypeException` is not thrown anymore
- The trait adds new methods and query scopes, which can collide with methods of your models (see the [upgrade guide](UPGRADING.md#new-methods))
- New dependencies `illuminate/console` and `illuminate/filesystem` (part of every Laravel app)
- All classes use `declare(strict_types=1)`

### Added

- Configuration per model with a method, constant or property: column, type of the attribute, defaults, casts, defaults in `toArray()`
- Type of the attribute: `PropertiesCast::Array` (default), `Object`, `Collection` and encrypted variants of all three
- Defaults (`propertyDefaults()`), only used when reading, never stored; `withPropertyDefaults()` / `withoutPropertyDefaults()` for `toArray()` / JSON
- Casts per property (`propertyCasts()`): `int`, `float`, `decimal:<digits>`, `bool`, `string`, `array`, `collection`, `date`, `datetime`, `immutable_date`, `immutable_datetime` (with format), backed enums (strict or `EnumCast::lenient()`), custom casts (`Contracts\PropertyCast`), also for nested keys
- Encryption of single properties (`encrypted`, `encrypted:<cast>`), hidden in `toArray()` / JSON, key rotation with `APP_PREVIOUS_KEYS`
- `props()` object with fluent, array and property access
- Methods `setProperties()`, `replaceProperties()`, `hasProperty()`, `forgetProperty()`, `getProperties()`, `getStoredProperties()`, `clearProperties()`, `pushProperty()`, `pullProperty()`, `incrementProperty()`, `decrementProperty()`, `toggleProperty()`, `onlyProperties()`, `exceptProperties()`
- Query scopes `whereProperty()`, `orWhereProperty()`, `whereProperties()`, `wherePropertyIn()`, `wherePropertyNotIn()`, `wherePropertyNull()`, `wherePropertyNotNull()`, `wherePropertyBetween()`, `wherePropertyNotBetween()`, `whereHasProperty()`, `whereDoesntHaveProperty()`, `wherePropertyContains()`, `wherePropertyDoesntContain()`, `wherePropertyLength()`, `orderByProperty()`, `orderByPropertyDesc()`. They use the casts and defaults, compare numbers as numbers and refuse encrypted properties
- Blueprint macros `properties()`, `dropProperties()`, `propertyIndex()` and `dropPropertyIndex()`
- `Properties::table()` and `Properties::for()` to change the properties of all rows in migrations (`rename()`, `forget()`, `set()`, `transform()`, `moveIn()`, `moveOut()`)
- Artisan command `make:properties-migration`
- Interface `Contracts\HasProperties`
- [Laravel Boost](https://laravel.com/docs/boost) guideline and upgrade skill for AI agents
- Test suite (Pest, 100 % coverage) on SQLite, MySQL, MariaDB and PostgreSQL, PHPStan level 8 and GitHub Actions workflow

### Fixed

- `getProperty()` ignored the default value
- A custom column (`$propertiesField`) was a fatal error, because the trait declared the property itself
- Without a manual `array` cast `getProperty()` silently returned `null` and `setProperty()` threw an exception
- PHP 7.4 was allowed by `composer.json`, but the code needed PHP 8.0
- The migration in the readme was not nullable, creating a model without properties failed on MySQL

### Changed

- Contact email changed to `git@maxham.net`

## 1.2.1 - 2026-01-25

- Fixed `getProperty()`, `setProperty()` and `emptyProperties()`: in 1.2 they read an undefined variable instead of the column and did not work

## 1.2 - 2026-01-25

- Added support for custom Fields
- Added support for Laravel 11+12
- Added support for PHP 8.3+8.4
- Added laravel pint
- Added the `CHANGELOG.md` file
- Added the `pint.json` file
- Added the `.gitattributes` file
- Added the `.editorconfig` file
- Added the `.gitignore` file

## 1.1 - 2023-06-18

- Added the `readme.md` file

## 1.0 - 2023-06-18

- Initial release
