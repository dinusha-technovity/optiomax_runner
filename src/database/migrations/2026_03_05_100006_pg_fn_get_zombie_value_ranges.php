<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION get_zombie_value_ranges()
        RETURNS TABLE (
            id            BIGINT,
            name          TEXT,
            label         TEXT,
            min_value     NUMERIC,
            max_value     NUMERIC,
            display_order INT
        )
        LANGUAGE sql STABLE AS $$
            SELECT
                id::BIGINT,
                name::TEXT,
                label::TEXT,
                min_value,
                max_value,
                display_order::INT
            FROM zombie_asset_value_ranges
            WHERE isactive = TRUE
            ORDER BY display_order;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_zombie_value_ranges();');
    }
};
