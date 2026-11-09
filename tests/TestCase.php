<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use TobyMaxham\LaravelProperties\LaravelPropertiesServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelPropertiesServiceProvider::class];
    }

    /**
     * SQLite by default. Run against other databases with e.g.
     * `DB_CONNECTION=mysql DB_PORT=3306 vendor/bin/pest` (see the GitHub workflow).
     */
    protected function defineEnvironment($app): void
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', 'sqlite' === $driver
            ? ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']
            : [
                'driver'   => $driver,
                'host'     => env('DB_HOST', '127.0.0.1'),
                'port'     => env('DB_PORT'),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'mysql' === $driver || 'mariadb' === $driver ? 'root' : 'postgres'),
                'password' => env('DB_PASSWORD', 'secret'),
                'charset'  => 'pgsql' === $driver ? 'utf8' : 'utf8mb4',
                'prefix'   => '',
            ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Close the connection after each test, MySQL and PostgreSQL limit the number of connections.
        $this->beforeApplicationDestroyed(fn () => DB::disconnect());

        Schema::dropIfExists('items');
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->properties();
            $table->properties('settings');
            $table->properties('meta');
            $table->properties('secret', encrypted: true);
            $table->timestamps();
        });
    }
}
