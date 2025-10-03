<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TruncateMultipleTenants extends Command
{
    protected $signature = 'tenants:truncate {prefix} {table}';
    protected $description = 'Truncate a specific table in multiple tenant databases with the specified prefix';

    public function handle()
    {
        $prefix = $this->argument('prefix');
        $table = $this->argument('table');

        // Fetch the tenant databases
        $tenants = $this->getDatabasesWithPrefix($prefix);

        foreach ($tenants as $tenant) {
            // Set the connection to the tenant database
            config([
                "database.connections.$tenant" => [
                    'driver' => 'pgsql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '5432'),
                    'database' => $tenant, 
                    'username' => env('DB_USERNAME', 'forge'),
                    'password' => env('DB_PASSWORD', ''),
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                ],
            ]);

            DB::purge($tenant); // Purge the connection
            DB::reconnect($tenant); // Reconnect to the tenant database

            // Directly truncate the specified table
            $this->truncateTable($table, $tenant);
        }

        $this->info('Truncation complete for all tenants.');
    }

    protected function getDatabasesWithPrefix(string $prefix): array
    {
        $query = "SELECT datname FROM pg_database WHERE datistemplate = false AND datname LIKE '{$prefix}%'";
        $databaseNames = DB::connection('pgsql')->select($query);

        return array_column($databaseNames, 'datname');
    }

    protected function truncateTable($table, $userDatabase)
    {
        // Check if the table exists in the current tenant database
        if (!DB::connection($userDatabase)->getSchemaBuilder()->hasTable($table)) {
            $this->error("Table {$table} does not exist in the {$userDatabase} database.");
            return;
        }

        // Directly execute the SQL to truncate the table
        DB::connection($userDatabase)->table($table)->truncate();

        Log::info("Table {$table} has been truncated in {$userDatabase} database.");
        $this->info("Table {$table} has been truncated in {$userDatabase} database.");
    }
}
