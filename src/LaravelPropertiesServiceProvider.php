<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties;

use InvalidArgumentException;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use TobyMaxham\LaravelProperties\Support\PropertyIndex;
use TobyMaxham\LaravelProperties\Console\MakePropertiesMigrationCommand;

class LaravelPropertiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerBlueprintMacros();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MakePropertiesMigrationCommand::class]);
        }
    }

    protected function registerBlueprintMacros(): void
    {
        /*
         * $table->properties();                          // json, nullable
         * $table->properties('settings', jsonb: true);   // jsonb on PostgreSQL
         * $table->properties(encrypted: true);           // text, for the encrypted casts
         */
        Blueprint::macro('properties', function (string $column = 'properties', bool $encrypted = false, bool $jsonb = false): ColumnDefinition {
            if ($encrypted && $jsonb) {
                throw new InvalidArgumentException('Encrypted properties are stored as text, they can not use jsonb.');
            }

            /** @var Blueprint $this */
            $definition = match (true) {
                $encrypted => $this->text($column),
                $jsonb     => $this->jsonb($column),
                default    => $this->json($column),
            };

            return $definition->nullable();
        });

        Blueprint::macro('dropProperties', function (string $column = 'properties'): void {
            /** @var Blueprint $this */
            $this->dropColumn($column);
        });

        /*
         * $table->propertyIndex('ui.theme');               // index for whereProperty('ui.theme', ...)
         * $table->propertyIndex('status', 'settings');     // on another column
         */
        Blueprint::macro('propertyIndex', function (string $key, string $column = 'properties', ?string $name = null, int $length = 191): void {
            /** @var Blueprint $this */
            PropertyIndex::add($this, $this->connection, $key, $column, $name, $length);
        });

        Blueprint::macro('dropPropertyIndex', function (string $key, string $column = 'properties', ?string $name = null): void {
            /** @var Blueprint $this */
            PropertyIndex::drop($this, $this->connection, $key, $column, $name);
        });
    }
}
