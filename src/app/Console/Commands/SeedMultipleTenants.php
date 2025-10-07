<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedMultipleTenants extends Command
{
    protected $signature = 'tenants:seed {prefix} {seeder?}';
    protected $description = 'Seed multiple tenant databases with the specified prefix';

    public function handle()
    {
        $prefix = $this->argument('prefix');
        $seeder = $this->argument('seeder') ?? 'DatabaseSeeder'; // Specify a default seeder if not provided

        // Fetch the tenant databases
        $tenants = $this->getDatabasesWithPrefix($prefix);

        foreach ($tenants as $tenant) {
            // $tenantName = $tenant->datname;
            // $this->info("Seeding tenant: {$tenantName}");

            // Set the connection to the tenant database
            $userDatabase = 'tenant'; // You can modify this based on your logic

            config([
                "database.connections.$tenant" => [
                    'driver' => 'pgsql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '5432'),
                    'database' => $tenant, // Use the tenant database name
                    'username' => env('DB_USERNAME', 'forge'),
                    'password' => env('DB_PASSWORD', ''),
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                ],
            ]);

            DB::purge($tenant); // Purge the connection
            DB::reconnect($tenant); // Reconnect to the tenant database

            // Run the seeder
            $this->seedData($seeder, $tenant);
        }

        $this->info('Seeding complete for all tenants.');
    }

    protected function getDatabasesWithPrefix(string $prefix): array
    {
        $query = "SELECT datname FROM pg_database WHERE datistemplate = false AND datname LIKE '{$prefix}%'";
        $databaseNames = DB::connection('pgsql')->select($query);

        return array_column($databaseNames, 'datname');
    }

    protected function seedData($seeder, $userDatabase)
    {
        // Call the specified seeder class with the --class option
        $this->call('db:seed', [
            '--database' => $userDatabase,
            '--class' => $seeder
        ]);
    }
}
