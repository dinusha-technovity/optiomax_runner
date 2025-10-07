<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Allow NULL values for specific columns
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN reason DROP NOT NULL;');
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN business_impact DROP NOT NULL;');
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN business_purpose DROP NOT NULL;');

        // Drop columns if they exist (PostgreSQL safe way)
        $columns = [
            'new_detail_type',
            'new_details',
            'kpi_type',
            'new_kpi_details',
            'upgrade_or_new',
        ];
        foreach ($columns as $column) {
            DB::statement(<<<SQL
                DO $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='asset_requisitions_items' AND column_name='{$column}') THEN
                        ALTER TABLE asset_requisitions_items DROP COLUMN {$column};
                    END IF;
                END$$;
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert columns to NOT NULL
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN reason SET NOT NULL;');
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN business_impact SET NOT NULL;');
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN business_purpose SET NOT NULL;');

        // Add dropped columns back (PostgreSQL safe way)
        $columns = [
            'new_detail_type' => "VARCHAR(255)",
            'new_details' => "TEXT",
            'kpi_type' => "VARCHAR(255)",
            'new_kpi_details' => "TEXT",
            'upgrade_or_new' => "VARCHAR(255)",
        ];
        foreach ($columns as $column => $type) {
            DB::statement(<<<SQL
                DO $$
                BEGIN
                    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='asset_requisitions_items' AND column_name='{$column}') THEN
                        ALTER TABLE asset_requisitions_items ADD COLUMN {$column} {$type} NULL;
                    END IF;
                END$$;
            SQL);
        }
    }
};
// This migration modifies the asset_requisitions_items table to allow null values for the reason, business_impact, and business_purpose columns.
// updated by sachin karunarathna