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
      DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION get_country_codes()
        RETURNS TABLE (
            id BIGINT,
            name_common VARCHAR(255),
            phone_code VARCHAR(255),
            flag VARCHAR(255)
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RETURN QUERY 
                SELECT
                countries.id,
                countries.name_common,
                countries.phone_code,
                countries.flag FROM countries;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_country_codes');
    }
};
