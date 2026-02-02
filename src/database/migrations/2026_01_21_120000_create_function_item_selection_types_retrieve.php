<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** 
     * Run the migrations.
     */ 
    public function up(): void
    {  
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_all_item_selection_types(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_item_selection_type_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                description TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- If both parameters are NULL, return all records
                IF p_tenant_id IS NULL AND p_item_selection_type_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All item selection types fetched successfully'::TEXT AS message,
                        a.id,
                        a.name::TEXT,
                        a.description::TEXT
                    FROM internal_asset_requisitions_item_selection_types a
                    WHERE a.deleted_at IS NULL
                    AND a.isactive = TRUE;
                    RETURN;
                END IF;

                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Validate item selection type ID (optional)
                IF p_item_selection_type_id IS NOT NULL AND p_item_selection_type_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid item selection type ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Item selection types fetched successfully'::TEXT AS message,
                    a.id,
                    a.name::TEXT,
                    a.description::TEXT
                FROM
                    internal_asset_requisitions_item_selection_types a
                WHERE (p_item_selection_type_id IS NULL OR a.id = p_item_selection_type_id)
                AND a.tenant_id = p_tenant_id
                AND a.deleted_at IS NULL
                AND a.isactive = TRUE;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_item_selection_types');
    }
};
