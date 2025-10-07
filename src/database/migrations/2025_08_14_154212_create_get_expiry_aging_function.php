<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION get_expiry_aging(p_tenant_id BIGINT)
            RETURNS TABLE (
                item_id BIGINT,
                item_name TEXT,
                lot_number TEXT,
                expiration_date DATE,
                days_to_expiry INT
            ) LANGUAGE plpgsql AS $$
            BEGIN
                RETURN QUERY
                SELECT i.id, i.item_name::TEXT, s.lot_number::TEXT, s.expiration_date,
                    CASE WHEN s.expiration_date IS NULL THEN NULL
                            ELSE (s.expiration_date - CURRENT_DATE)
                    END AS days_to_expiry
                FROM items i
                JOIN item_stock_levels s ON i.id = s.item_id
                WHERE s.tenant_id = p_tenant_id;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            DROP FUNCTION IF EXISTS get_expiry_aging(BIGINT);
        ");
    }
};
