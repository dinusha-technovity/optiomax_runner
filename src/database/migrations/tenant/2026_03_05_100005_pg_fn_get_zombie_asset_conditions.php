<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION get_zombie_asset_conditions()
        RETURNS TABLE (
            id            BIGINT,
            name          TEXT,
            label         TEXT,
            display_order INT
        )
        LANGUAGE sql STABLE AS $$
            SELECT
                id::BIGINT,
                name::TEXT,
                label::TEXT,
                display_order::INT
            FROM zombie_asset_conditions
            WHERE isactive = TRUE
            ORDER BY display_order;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_zombie_asset_conditions();');
    }
};
