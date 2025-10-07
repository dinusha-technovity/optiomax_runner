<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DropAndSeedTenants extends Command
{
    protected $signature = 'tenants:drop-seed {prefix}';
    protected $description = 'Drop data from tenants with the specified prefix and seed new data';

    public function handle()
    {
        $prefix = $this->argument('prefix');
        // Fetch the tenant databases
        $tenants = DB::select("SELECT datname FROM pg_database WHERE datname LIKE '{$prefix}%'");

        foreach ($tenants as $tenant) {
            $tenantName = $tenant->datname;
            $this->info("Processing tenant: {$tenantName}");

            // Set the connection to the tenant database
            config(['database.connections.tenant.database' => $tenantName]);
            DB::purge('tenant');

            // Drop data from the table
            DB::connection('tenant')->table('your_table_name')->truncate();

            // Seed new data
            $this->seedData();
        }
    }

    protected function seedData()
    {
        // Implement your seeding logic here
        // Example: DB::connection('tenant')->table('your_table_name')->insert([...]);
        // You can use factories or custom logic to seed data
    }
}
