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
            CREATE OR REPLACE FUNCTION get_tenant_packages_list(
                IN p_tenant_packages_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                name TEXT,
                type TEXT,
                price INT,
                discount_price INT,
                description TEXT,
                credits INT,
                workflows INT,
                users INT,
                support BOOLEAN
            )
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- Validate supplier ID if provided
                IF p_tenant_packages_id IS NOT NULL AND p_tenant_packages_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant package ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Case 4: Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'tenant package fetched successfully'::TEXT AS message,
                    tp.id,
                    tp.name::TEXT,
                    tp.type::TEXT,
                    tp.price::INT,
                    tp.discount_price::INT,
                    tp.description::TEXT,
                    tp.credits::INT,
                    tp.workflows::INT,
                    tp.users::INT,
                    tp.support BOOLEAN
                FROM tenant_packages tp
                WHERE (p_tenant_packages_id IS NULL OR tp.id = p_tenant_packages_id)
                AND tp.deleted_at IS NULL
                AND tp.isactive = TRUE;

            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_tenant_packages_list');
    }
};
