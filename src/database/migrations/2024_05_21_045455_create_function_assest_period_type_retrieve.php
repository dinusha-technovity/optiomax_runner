<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_RETRIEVE_PERIOD_TYPES(   
        //         IN p_period_type_id INT DEFAULT NULL
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         DROP TABLE IF EXISTS period_type_from_store_procedure;
            
        //         IF p_period_type_id IS NOT NULL AND p_period_type_id <= 0 THEN
        //             RAISE EXCEPTION 'Invalid p_period_type_id: %', p_period_type_id;
        //         END IF;
            
        //         CREATE TEMP TABLE period_type_from_store_procedure AS
        //         SELECT
        //             arpt.id AS period_type_id,
        //             arpt.name,
        //             arpt.description,
        //             arpt.created_at,
        //             arpt.updated_at
        //         FROM
        //             asset_requisition_period_types arpt
        //         WHERE
        //             (arpt.id = p_period_type_id OR p_period_type_id IS NULL OR p_period_type_id = 0)
        //             AND arpt.deleted_at IS NULL
        //             AND arpt."isactive" = TRUE;
        //     END;
        //     $$;
        //     SQL
        // );
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_asset_period_type(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_period_type_id INT DEFAULT NULL
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
                IF p_tenant_id IS NULL AND p_period_type_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All period types fetched successfully'::TEXT AS message,
                        arpt.id,
                        arpt.name::TEXT,
                        arpt.description::TEXT
                    FROM asset_requisition_period_types arpt
                    WHERE arpt.deleted_at IS NULL
                    AND arpt.isactive = TRUE;
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

                -- Validate period type ID (optional)
                IF p_period_type_id IS NOT NULL AND p_period_type_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid period type ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Period types fetched successfully'::TEXT AS message,
                    arpt.id,
                    arpt.name::TEXT,
                    arpt.description::TEXT
                FROM
                    asset_requisition_period_types arpt
                WHERE (p_period_type_id IS NULL OR arpt.id = p_period_type_id)
                AND arpt.tenant_id = p_tenant_id
                AND arpt.deleted_at IS NULL
                AND arpt.isactive = TRUE;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_period_type');
    }
};