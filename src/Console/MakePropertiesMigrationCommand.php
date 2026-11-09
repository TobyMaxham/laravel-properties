<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelProperties\Console;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * php artisan make:properties-migration users
 * php artisan make:properties-migration users --column=settings --jsonb
 * php artisan make:properties-migration users --encrypted --index=ui.theme
 */
class MakePropertiesMigrationCommand extends Command
{
    protected $signature = 'make:properties-migration
        {table : The table that gets the column}
        {--column=properties : The name of the column}
        {--encrypted : Store the column as text, for the encrypted casts}
        {--jsonb : Use jsonb on PostgreSQL}
        {--index=* : Add an index for these properties}
        {--path= : The location of the migration (default: database/migrations)}';

    protected $description = 'Create a migration that adds a properties column to a table';

    public function handle(Filesystem $files): int
    {
        $table = $this->stringValue($this->argument('table'), '');
        $column = $this->stringValue($this->option('column'), 'properties');
        $encrypted = true === $this->option('encrypted');
        $jsonb = true === $this->option('jsonb');
        $indexes = array_values(array_filter((array) $this->option('index'), is_string(...)));
        $customPath = $this->stringValue($this->option('path'), '');

        if ($encrypted && ($jsonb || [] !== $indexes)) {
            $this->components->error('Encrypted properties are stored as text, they can not use --jsonb or --index.');

            return self::FAILURE;
        }

        $path = '' !== $customPath ? base_path($customPath) : $this->laravel->databasePath('migrations');
        $file = $path.'/'.date('Y_m_d_His').'_add_'.Str::snake($column).'_to_'.Str::snake($table).'_table.php';

        $files->ensureDirectoryExists($path);
        $files->put($file, $this->stub($table, $column, $encrypted, $jsonb, $indexes));

        $this->components->info(sprintf('Migration [%s] created successfully.', $file));

        return self::SUCCESS;
    }

    private function stringValue(mixed $value, string $default): string
    {
        return is_string($value) && '' !== $value ? $value : $default;
    }

    /**
     * @param  list<string>  $indexes
     */
    private function stub(string $table, string $column, bool $encrypted, bool $jsonb, array $indexes): string
    {
        $arguments = array_filter([
            'properties' !== $column ? var_export($column, true) : null,
            $encrypted ? 'encrypted: true' : null,
            $jsonb ? 'jsonb: true' : null,
        ]);
        $columnArgument = 'properties' !== $column ? ', '.var_export($column, true) : '';

        $up = ['            $table->properties('.implode(', ', $arguments).');'];
        $down = [];

        foreach ($indexes as $index) {
            $up[] = '            $table->propertyIndex('.var_export($index, true).$columnArgument.');';
            $down[] = '            $table->dropPropertyIndex('.var_export($index, true).$columnArgument.');';
        }

        $down[] = '            $table->dropProperties('.('properties' !== $column ? var_export($column, true) : '').');';

        $table = var_export($table, true);
        $up = implode("\n", $up);
        $down = implode("\n", $down);

        return <<<PHP
            <?php

            use Illuminate\\Database\\Migrations\\Migration;
            use Illuminate\\Database\\Schema\\Blueprint;
            use Illuminate\\Support\\Facades\\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::table({$table}, function (Blueprint \$table) {
            {$up}
                    });
                }

                public function down(): void
                {
                    Schema::table({$table}, function (Blueprint \$table) {
            {$down}
                    });
                }
            };

            PHP;
    }
}
