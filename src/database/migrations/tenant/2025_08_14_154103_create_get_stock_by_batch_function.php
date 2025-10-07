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
            CREATE OR REPLACE FUNCTION get_stock_by_batch(p_tenant_id BIGINT, p_item_id BIGINT)
            RETURNS TABLE (
                lot_number TEXT,
                expiration_date DATE,
                qty NUMERIC
            ) LANGUAGE plpgsql AS $$
            BEGIN
                RETURN QUERY
                SELECT s.lot_number::TEXT, 
                    s.expiration_date, 
                    s.quantity::NUMERIC
                FROM item_stock_levels s
                WHERE s.tenant_id = p_tenant_id
                AND s.item_id = p_item_id
                ORDER BY s.expiration_date NULLS LAST;
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
            DROP FUNCTION IF EXISTS get_stock_by_batch(BIGINT, BIGINT);
        ");
    }
};
