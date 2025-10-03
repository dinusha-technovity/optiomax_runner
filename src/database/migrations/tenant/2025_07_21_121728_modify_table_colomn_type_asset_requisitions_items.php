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
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN expected_depreciation_value TYPE numeric(5,2) USING expected_depreciation_value::numeric;');
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN business_purpose TYPE text USING business_purpose::text;');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN expected_depreciation_value TYPE numeric USING expected_depreciation_value::numeric;');
        DB::statement('ALTER TABLE asset_requisitions_items ALTER COLUMN business_purpose TYPE varchar(255) USING business_purpose::varchar(255);');
    }
};
