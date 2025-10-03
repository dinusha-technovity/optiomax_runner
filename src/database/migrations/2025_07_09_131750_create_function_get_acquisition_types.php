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
        \DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_acquisition_types(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_acquisition_type_id BIGINT DEFAULT NULL
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
                IF p_tenant_id IS NULL AND p_acquisition_type_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT,
                        'All acquisition types fetched successfully'::TEXT,
                        at.id,
                        at.name::TEXT
                    FROM asset_requisition_acquisition_types at
                    WHERE at.deleted_at IS NULL
                    AND at.id IS NOT NULL;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT;
                    RETURN;
                END IF;

                IF p_acquisition_type_id IS NOT NULL AND p_acquisition_type_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT,
                        'Invalid acquisition type ID provided'::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Acquisition types fetched successfully'::TEXT,
                    at.id,
                    at.name::TEXT
                FROM asset_requisition_acquisition_types at
                WHERE (p_acquisition_type_id IS NULL OR at.id = p_acquisition_type_id)
                AND (p_tenant_id IS NULL OR at.tenant_id = p_tenant_id)
                AND at.deleted_at IS NULL;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::unprepared('DROP FUNCTION IF EXISTS get_acquisition_types');
    }
};
