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
            CREATE OR REPLACE FUNCTION get_supplier_list(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_supplier_id INT DEFAULT NULL
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
                -- Case 1: Return all records when both parameters are NULL
                IF p_tenant_id IS NULL AND p_supplier_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All supplier list fetched successfully'::TEXT AS message,
                        s.id,
                        s.name::TEXT,
                        s.description::TEXT
                    FROM suppliers s
                    WHERE s.deleted_at IS NULL
                    AND s.isactive = TRUE;
                    RETURN;
                END IF;

                -- Case 2: Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Case 3: Validate supplier ID if provided
                IF p_supplier_id IS NOT NULL AND p_supplier_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid supplier ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Case 4: Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset supplier fetched successfully'::TEXT AS message,
                    s.id,
                    s.name::TEXT,
                    s.description::TEXT
                FROM suppliers s
                WHERE (p_supplier_id IS NULL OR s.id = p_supplier_id)
                AND s.tenant_id = p_tenant_id
                AND s.supplier_reg_status = 'APPROVED'
                AND s.deleted_at IS NULL
                AND s.isactive = TRUE;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_supplier_list');
    }
};