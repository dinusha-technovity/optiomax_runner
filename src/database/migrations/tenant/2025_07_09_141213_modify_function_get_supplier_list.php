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
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS get_supplier_list(BIGINT, INT);

            CREATE OR REPLACE FUNCTION get_supplier_list(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_supplier_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                description TEXT,
                email TEXT,
                reg_status TEXT,
                supplier_rating BIGINT
            ) 
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF p_tenant_id IS NULL AND p_supplier_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS',
                        'All supplier list fetched successfully',
                        s.id,
                        s.name::TEXT,
                        s.description,
                        s.email::TEXT,
                        s.supplier_reg_status::TEXT,
                        s.supplier_rating
                    FROM suppliers s
                    WHERE s.deleted_at IS NULL
                    AND s.isactive = TRUE;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE',
                        'Invalid tenant ID provided',
                        NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                IF p_supplier_id IS NOT NULL AND p_supplier_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE',
                        'Invalid supplier ID provided',
                        NULL, NULL, NULL, NULL, NULL, NULL;
                    RETURN;
                END IF;

                IF p_supplier_id = 0 THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS',
                        'All suppliers (reg status ignored) fetched successfully',
                        s.id,
                        s.name::TEXT,
                        s.description,
                        s.email::TEXT,
                        s.supplier_reg_status::TEXT,
                        s.supplier_rating
                    FROM suppliers s
                    WHERE s.tenant_id = p_tenant_id
                    AND s.deleted_at IS NULL
                    AND s.isactive = TRUE;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS',
                    'Asset supplier fetched successfully',
                    s.id,
                    s.name::TEXT,
                    s.description,
                    s.email::TEXT,
                    s.supplier_reg_status::TEXT,
                    s.supplier_rating
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