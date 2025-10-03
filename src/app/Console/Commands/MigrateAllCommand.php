<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
class MigrateAllCommand extends Command
{
    protected $signature = 'tenantss:migrates:all {prefix} {--path=}';
    protected $description = 'Run migrations for all databases with a specific prefix';

    public function handle()
    {
        $prefix = $this->argument('prefix');
        $migrationPath = $this->option('path') ?? 'database/migrations/tenant';
        $tenantData = $this->getDatabasesWithPrefix($prefix);

        if (empty($tenantData)) {
            $this->info('No databases found with the specified prefix.');
            return;
        }
 
        foreach ($tenantData as $tenant) {
            // $tenantName = $tenant->datname;
            // $this->info("Seeding tenant: {$tenantName}");
            $database = $tenant['database'];
            $username = $tenant['username'];
            $password = $tenant['password'];
            // Set the connection to the tenant database

            config([
                "database.connections.$database" => [
                    'driver' => 'pgsql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '5432'),
                    'database' => $database,
                    'username' => $username ?: env('DB_USERNAME', 'forge'),
                    'password' => $password ?: env('DB_PASSWORD', ''),
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                ],
            ]);
            DB::purge($database); // Purge the connection
            DB::reconnect($database); // Reconnect to the tenant database
            
            $this->runMigrations($database, $migrationPath);
            // Run the seeder
           
        }

        $this->info('Migration completed for all tenants.');
    }

    protected function getDatabasesWithPrefix(string $prefix): array
    {
        // First, get all database names with the specified prefix
        $query = "SELECT datname FROM pg_database WHERE datistemplate = false AND datname LIKE '{$prefix}%'";
        $databaseRecords = DB::connection('pgsql')->select($query);
        $databaseNames = array_column($databaseRecords, 'datname');
        
    
        // Next, get the associated credentials from tenants table
        $tenantCredentials = [];
        foreach ($databaseNames as $dbName) {
            $tenant = DB::table('tenants')
            ->where('db_name', $dbName)
            ->select('db_name', 'db_user', 'db_password')
            ->first();
            
            Log::info('Database tenant: ', $tenant ? (array)$tenant : []);
            
            if ($tenant) {
                $tenantCredentials[] = [
                    'database' => $tenant->db_name,
                    'username' => $tenant->db_user,
                    'password' => $tenant->db_password
                ];
            } else {
                // If no credentials found, use the database name only
                $tenantCredentials[] = [
                    'database' => $dbName,
                    'username' => null,
                    'password' => null
                ];
            }
        }
        
        return $tenantCredentials;
    }
    

    protected function runMigrations(string $userDatabase, string $migrationPath): void
    {
        $this->call('migrate', [
            '--database' => $userDatabase,
            '--path' => $migrationPath,
        ]);

        $this->info("Executing migrations for database '$userDatabase'.");
        $this->line(Artisan::output());
    }
}

// Usage example: single migration run example
// php artisan tenantss:migrates:all tenant_ --path=database/migrations/tenant/2025_06_09_172413_modify_suppliers_table.php
