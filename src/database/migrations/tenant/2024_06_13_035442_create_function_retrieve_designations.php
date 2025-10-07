<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    { 
        // DB::unprepared(
        //     "CREATE OR REPLACE PROCEDURE STORE_PROCEDURE_RETRIEVE_DESIGNATIONS(
        //         IN _tenant_id BIGINT,
        //         IN p_designation_id INT DEFAULT NULL
        //     ) AS
        //     $$
        //         BEGIN
        //             DROP TABLE IF EXISTS designations_from_store_procedure;
                
        //             IF p_designation_id IS NOT NULL AND p_designation_id <= 0 THEN
        //                 RAISE EXCEPTION 'Invalid p_designation_id: %', p_designation_id;
        //             END IF;
                
        //             CREATE TEMP TABLE designations_from_store_procedure AS
        //             SELECT * FROM designations 
        //                 WHERE (designations.id = p_designation_id 
        //                 OR p_designation_id IS NULL 
        //                 OR p_designation_id = 0)
        //                 AND designations.tenant_id = _tenant_id
        //                 AND designations.deleted_at IS NULL
        //                 AND designations.isactive = TRUE;
        //         END
        //     $$ LANGUAGE plpgsql;"
        // );
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_designations(
                _tenant_id BIGINT,
                p_designation_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                designation TEXT
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate tenant ID
                IF _tenant_id IS NULL OR _tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS designation;
                    RETURN;
                END IF;
            
                -- Validate designation ID (optional)
                IF p_designation_id IS NOT NULL AND p_designation_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid designation ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS designation;
                    RETURN;
                END IF;
            
                -- Check if any matching records exist
                IF NOT EXISTS (
                    SELECT 1 
                    FROM designations d
                    WHERE (p_designation_id IS NULL OR d.id = p_designation_id)
                    AND d.tenant_id = _tenant_id
                    AND d.deleted_at IS NULL
                    AND d.isactive = TRUE
                ) THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching designations found'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS designation;
                    RETURN;
                END IF;
            
                -- Return the matching records
                RETURN QUERY
                SELECT 
                    'SUCCESS'::TEXT AS status,
                    'Designations fetched successfully'::TEXT AS message,
                    d.id,
                    d.designation::TEXT
                FROM designations d
                WHERE (p_designation_id IS NULL OR d.id = p_designation_id)
                AND d.tenant_id = _tenant_id
                AND d.deleted_at IS NULL
                AND d.isactive = TRUE;
            
            END;
            $$;
        SQL);
        
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_designations');
    }
};