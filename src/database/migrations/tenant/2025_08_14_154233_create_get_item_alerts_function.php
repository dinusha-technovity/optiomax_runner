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
            CREATE OR REPLACE FUNCTION get_item_alerts(p_tenant_id BIGINT)
                RETURNS TABLE (
                    alert_id BIGINT,
                    item_id BIGINT,
                    item_name TEXT,
                    item_description TEXT,
                    category_id BIGINT,
                    category_name TEXT,
                    unit_of_measure_id BIGINT,
                    unit_of_measure_name TEXT,
                    alert_type TEXT,
                    message TEXT,
                    max_inventory_level INT,
                    min_inventory_level INT,
                    image_links JSONB,
                    created_at TIMESTAMP
                ) LANGUAGE plpgsql AS $$
                BEGIN
                    RETURN QUERY
                    SELECT 
                        ia.id AS alert_id,
                        ia.item_id,
                        i.item_name::TEXT,
                        i.item_description::TEXT,
                        i.category_id,
                        c.name::TEXT AS category_name,
                        i.unit_of_measure_id,
                        m.name::TEXT AS unit_of_measure_name,
                        ia.alert_type::TEXT,
                        ia.message::TEXT,
                        CASE WHEN ia.alert_type = 'OVER_STOCK' THEN i.max_inventory_level ELSE NULL END AS max_inventory_level,
                        CASE WHEN ia.alert_type = 'LOW_STOCK' THEN i.min_inventory_level ELSE NULL END AS min_inventory_level,
                        i.image_links,
                        ia.created_at
                    FROM item_alerts ia
                    JOIN items i 
                        ON ia.item_id = i.id 
                    AND ia.tenant_id = i.tenant_id
                    LEFT JOIN asset_categories c 
                        ON i.category_id = c.id
                    LEFT JOIN measurements m 
                        ON i.unit_of_measure_id = m.id
                    WHERE ia.tenant_id = p_tenant_id
                    ORDER BY ia.created_at DESC;
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
            DROP FUNCTION IF EXISTS get_item_alerts(BIGINT);
        ");
    }
};
