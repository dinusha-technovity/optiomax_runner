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
            CREATE OR REPLACE FUNCTION get_stock_summary(p_tenant_id BIGINT)
            RETURNS TABLE (
                item_id BIGINT,
                item_name TEXT,
                total_qty NUMERIC
            ) LANGUAGE plpgsql AS $$
            BEGIN
                RETURN QUERY
                SELECT i.id,
                    i.item_name::TEXT,
                    COALESCE(SUM(s.quantity)::NUMERIC, 0) AS total_qty
                FROM items i
                LEFT JOIN item_stock_levels s 
                ON i.id = s.item_id AND s.tenant_id = p_tenant_id
                WHERE i.tenant_id = p_tenant_id
                GROUP BY i.id, i.item_name
                ORDER BY i.item_name;
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
            DROP FUNCTION IF EXISTS get_stock_summary(BIGINT);
        ");
    }
};
