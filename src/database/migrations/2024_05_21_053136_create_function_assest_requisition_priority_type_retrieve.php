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
        // DB::unprepared(<<<SQL
        //     CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_RETRIEVE_PRIORITY_TYPES( 
        //         IN p_priority_type_id INT DEFAULT NULL 
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         DROP TABLE IF EXISTS priority_type_from_store_procedure;
            
        //         IF p_priority_type_id IS NOT NULL AND p_priority_type_id <= 0 THEN
        //             RAISE EXCEPTION 'Invalid p_priority_type_id: %', p_priority_type_id;
        //         END IF;
            
        //         CREATE TEMP TABLE priority_type_from_store_procedure AS
        //         SELECT
        //             arprt.id AS priority_type_id,
        //             arprt.name,
        //             arprt.description,
        //             arprt.created_at,
        //             arprt.updated_at
        //         FROM
        //             asset_requisition_priority_types arprt
        //         WHERE
        //             (arprt.id = p_priority_type_id OR p_priority_type_id IS NULL OR p_priority_type_id = 0)
        //             AND arprt.deleted_at IS NULL
        //             AND arprt."isactive" = TRUE;
        //     END;
        //     $$;
        //     SQL
        // );
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_priority_type(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_priority_type_id INT DEFAULT NULL
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
                IF p_tenant_id IS NULL AND p_priority_type_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All priority types fetched successfully'::TEXT AS message,
                        arprt.id,
                        arprt.name::TEXT,
                        arprt.description::TEXT
                    FROM asset_requisition_priority_types arprt
                    WHERE arprt.deleted_at IS NULL
                    AND arprt.isactive = TRUE;
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

                -- Validate availability type ID (optional)
                IF p_priority_type_id IS NOT NULL AND p_priority_type_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid priority types ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset priority types fetched successfully'::TEXT AS message,
                    arprt.id,
                    arprt.name::TEXT,
                    arprt.description::TEXT
                FROM
                    asset_requisition_priority_types arprt
                WHERE (p_priority_type_id IS NULL OR arprt.id = p_priority_type_id)
                AND arprt.tenant_id = p_tenant_id
                AND arprt.deleted_at IS NULL
                AND arprt.isactive = TRUE;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_priority_type');
    }
};