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
        //     CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_RETRIEVE_AVAILABILITY_TYPES(  
        //         IN p_availability_type_id INT DEFAULT NULL 
        //     )
        //     LANGUAGE plpgsql
        //     AS $$
        //     BEGIN
        //         DROP TABLE IF EXISTS availability_type_from_store_procedure;
            
        //         IF p_availability_type_id IS NOT NULL AND p_availability_type_id <= 0 THEN
        //             RAISE EXCEPTION 'Invalid p_availability_type_id: %', p_availability_type_id;
        //         END IF;
            
        //         CREATE TEMP TABLE availability_type_from_store_procedure AS
        //         SELECT
        //             arat.id AS availability_type_id,
        //             arat.name,
        //             arat.description,
        //             arat.created_at,
        //             arat.updated_at
        //         FROM
        //             asset_requisition_availability_types arat
        //         WHERE
        //             (arat.id = p_availability_type_id OR p_availability_type_id IS NULL OR p_availability_type_id = 0)
        //             AND arat.deleted_at IS NULL
        //             AND arat."isactive" = TRUE;
        //     END;
        //     $$;
        //     SQL
        // );
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_asset_availability_type(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_availability_type_id INT DEFAULT NULL
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
                IF p_tenant_id IS NULL AND p_availability_type_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All asset availability types fetched successfully'::TEXT AS message,
                        arat.id,
                        arat.name::TEXT,
                        arat.description::TEXT
                    FROM asset_requisition_availability_types arat
                    WHERE arat.deleted_at IS NULL
                    AND arat.isactive = TRUE;
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
                IF p_availability_type_id IS NOT NULL AND p_availability_type_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid availability type ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset availability types fetched successfully'::TEXT AS message,
                    arat.id,
                    arat.name::TEXT,
                    arat.description::TEXT
                FROM
                    asset_requisition_availability_types arat
                WHERE (p_availability_type_id IS NULL OR arat.id = p_availability_type_id)
                AND arat.tenant_id = p_tenant_id
                AND arat.deleted_at IS NULL
                AND arat.isactive = TRUE;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_availability_type');
    }
};