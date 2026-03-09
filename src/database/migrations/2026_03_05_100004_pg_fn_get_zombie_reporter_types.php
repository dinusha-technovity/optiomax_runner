<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION get_zombie_reporter_types()
        RETURNS TABLE (
            id          BIGINT,
            name        TEXT,
            label       TEXT,
            description TEXT
        )
        LANGUAGE sql STABLE AS $$
            SELECT
                id::BIGINT,
                name::TEXT,
                label::TEXT,
                description::TEXT
            FROM zombie_asset_reporter_types
            WHERE isactive = TRUE
            ORDER BY name;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_zombie_reporter_types();');
    }
};
