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
        CREATE OR REPLACE FUNCTION get_warrenty_condition_types_list(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_warrenty_condition_type_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT
            ) 
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF p_tenant_id IS NULL AND p_warrenty_condition_type_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All warranty condition types list fetched successfully'::TEXT AS message,
                        wct.id,
                        wct.name::TEXT
                    FROM warrenty_condition_types wct
                    WHERE wct.deleted_at IS NULL
                    AND wct.isactive = TRUE;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name;
                    RETURN;
                END IF;

                IF p_warrenty_condition_type_id IS NOT NULL AND p_warrenty_condition_type_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid warranty condition type ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Warranty condition types fetched successfully'::TEXT AS message,
                    wct.id,
                    wct.name::TEXT
                FROM warrenty_condition_types wct
                WHERE (p_warrenty_condition_type_id IS NULL OR wct.id = p_warrenty_condition_type_id)
                AND wct.tenant_id = p_tenant_id
                AND wct.deleted_at IS NULL
                AND wct.isactive = TRUE;
            END; 
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_warrenty_condition_types_list(BIGINT, BIGINT);');
    }
};
