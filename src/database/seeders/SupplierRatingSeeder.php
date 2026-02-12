<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierRatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder truncates supplier rating related tables and recalculates
     * ratings based on existing data in asset_items, asset_maintenance_incident_reports,
     * and procurement_attempt_request_items.
     */
    public function run(): void
    {
        $this->command->info('Starting Supplier Rating Seeder...');

        // Step 1: Truncate rating-related tables (order matters due to FK constraints)
        $this->truncateRatingTables();

        // Step 2: Get all unique tenant IDs from suppliers
        $tenantIds = DB::table('suppliers')
            ->whereNotNull('tenant_id')
            ->distinct()
            ->pluck('tenant_id');

        if ($tenantIds->isEmpty()) {
            $this->command->warn('No tenants found with suppliers. Skipping seeder.');
            return;
        }

        $this->command->info("Found {$tenantIds->count()} tenant(s) to process.");

        foreach ($tenantIds as $tenantId) {
            $this->command->info("Processing tenant ID: {$tenantId}");
            $this->seedTenantRatings($tenantId);
        }

        $this->command->info('Supplier Rating Seeder completed successfully!');
    }

    /**
     * Truncate all supplier rating related tables.
     */
    private function truncateRatingTables(): void
    {
        $this->command->info('Truncating rating tables...');

        // Disable FK checks and truncate in correct order
        DB::statement('TRUNCATE TABLE supplier_rating_events RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE supplier_rating_summary RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE supplier_asset_counters RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE supplier_asset_global_stats CASCADE');

        $this->command->info('Rating tables truncated.');
    }

    /**
     * Seed ratings for a specific tenant.
     */
    private function seedTenantRatings(int $tenantId): void
    {
        // Step 1: Calculate and insert asset counts per supplier
        $supplierAssetCounts = DB::table('asset_items')
            ->select('supplier', DB::raw('COUNT(*) as asset_count'))
            ->where('tenant_id', $tenantId)
            ->whereNotNull('supplier')
            ->where('isactive', true)
            ->groupBy('supplier')
            ->get();

        if ($supplierAssetCounts->isEmpty()) {
            $this->command->warn("  No suppliers with assets found for tenant {$tenantId}.");
        }

        $highestAssetCount = 0;
        $highestSupplierId = null;

        foreach ($supplierAssetCounts as $record) {
            // Insert into supplier_asset_counters
            DB::table('supplier_asset_counters')->insert([
                'supplier_id' => $record->supplier,
                'asset_count' => $record->asset_count,
                'tenant_id' => $tenantId,
                'updated_at' => now(),
            ]);

            // Track the highest
            if ($record->asset_count > $highestAssetCount) {
                $highestAssetCount = $record->asset_count;
                $highestSupplierId = $record->supplier;
            }
        }

        // Step 2: Insert/Update global stats for this tenant
        DB::table('supplier_asset_global_stats')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'highest_asset_count' => $highestAssetCount,
                'highest_supplier_id' => $highestSupplierId,
                'updated_at' => now(),
            ]
        );

        $this->command->info("  Inserted {$supplierAssetCounts->count()} supplier asset counters.");
        $this->command->info("  Global stats: highest_asset_count={$highestAssetCount}, highest_supplier_id={$highestSupplierId}");

        // Step 3: Get all suppliers for this tenant and calculate their ratings
        $suppliers = DB::table('suppliers')
            ->where('tenant_id', $tenantId)
            ->where('isactive', true)
            ->pluck('id');

        $this->command->info("  Calculating ratings for {$suppliers->count()} supplier(s)...");

        foreach ($suppliers as $supplierId) {
            try {
                // Call the calculate_supplier_rating function with FULL_RECALC
                DB::statement(
                    "SELECT calculate_supplier_rating(?, 'FULL_RECALC', '{}'::JSONB, ?)",
                    [$supplierId, $tenantId]
                );
            } catch (\Exception $e) {
                $this->command->error("  Failed to calculate rating for supplier {$supplierId}: " . $e->getMessage());
            }
        }

        $this->command->info("  Tenant {$tenantId} processing complete.");
    }
}
