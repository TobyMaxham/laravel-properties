## Laravel Properties (tobymaxham/laravel-properties)

This package stores flexible properties of Eloquent models in one JSON column, with defaults, casts, enums, encryption, query scopes and migration helpers.

- Add `use \TobyMaxham\LaravelProperties\HasProperties;` to the model and a nullable column with `$table->properties()` (or `php artisan make:properties-migration {table}`). Do not add an `array` cast for the column, the trait adds its own cast.
- Read and write with "dot" notation: `getProperty('ui.theme', $default)`, `setProperty()`, `setProperties([...])`, `hasProperty()`, `forgetProperty()`, `getProperties()`. The model is not saved, call `save()`.
- Fluent access: `$model->props()->theme`, `$model->props()['ui.size']`, `$model->props()->set(...)->increment(...)`.
- Configure per model with a method, constant or property: `propertiesColumn()` / `PROPERTIES_COLUMN`, `propertiesCast()` / `PROPERTIES_CAST`, `propertyDefaults()` / `$propertyDefaults`, `propertyCasts()` / `$propertyCasts`, `serializePropertyDefaults()`. Never redeclare these as properties in a trait.
- Use defaults instead of storing default values. Defaults are only used when reading and are never stored.
- Use `propertyCasts()` for types instead of converting manually: `'logins' => 'int'`, `'birthday' => 'date'`, `'status' => Status::class`, `'price' => 'decimal:2'`, nested keys like `'address.country' => Country::class`. Writing invalid values throws `InvalidPropertyValueException`.
- Secrets use the cast `'encrypted'` or `'encrypted:<cast>'`. Never give encrypted properties a default, never query them (scopes throw `CannotQueryEncryptedPropertyException`), they are hidden in `toArray()`.
- Query with the scopes, not with raw JSON paths: `whereProperty()`, `orWhereProperty()`, `whereProperties([...])`, `wherePropertyIn()`, `wherePropertyNull()`, `wherePropertyBetween()`, `whereHasProperty()`, `wherePropertyContains()`, `wherePropertyLength()`, `orderByProperty()`. They use the casts and defaults and compare numbers as numbers.
- `$model->properties` returns the stored values without defaults and property casts. Use `getProperty()` / `props()` for cast values.
- Do not use dots in property keys.
- Add indexes for often queried properties with `$table->propertyIndex('ui.theme')`. On MariaDB query the generated column (`where('properties_ui_theme', ...)`) to use the index.
- Change existing data in migrations with `Properties::table('users')` (plain JSON) or `Properties::for(User::class)` (with casts and encryption): `->rename()`, `->forget()`, `->set(..., onlyIfMissing: true)`, `->transform()`, `->moveIn()`, `->moveOut()`, `->run()`. Do not loop over models and call `save()`.
- In tests, set properties with `setProperty()` / `setProperties()` and assert with `getProperty()`, not with the raw JSON (MySQL reorders keys).
- For upgrades between major versions use the skill `laravel-properties-upgrade`.
