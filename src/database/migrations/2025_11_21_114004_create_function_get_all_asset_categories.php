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

        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_all_asset_categories'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

           
            CREATE OR REPLACE FUNCTION get_all_asset_categories(
                p_tenant_id BIGINT,
                p_asset_categories_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                category_name TEXT,
                category_description TEXT,
                reading_parameters JSON,
                asset_type BIGINT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                category_count INT;
            BEGIN
                -- Validate tenant ID
                IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid tenant ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS category_name,
                        NULL::TEXT AS category_description,
                        NULL::JSON AS reading_parameters,
                        NULL::BIGINT AS asset_type;

                    RETURN;
                END IF;

                -- Validate category ID (optional)
                IF p_asset_categories_id IS NOT NULL AND p_asset_categories_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid category ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS category_name,
                        NULL::TEXT AS category_description,
                        NULL::JSON AS reading_parameters,
                        NULL::BIGINT AS asset_type;
                    RETURN;
                END IF;

                -- Check if any matching records exist
                SELECT COUNT(*) INTO category_count
                FROM asset_categories ac
                WHERE 
                    (p_asset_categories_id IS NULL OR ac.id = p_asset_categories_id)
                    AND ac.tenant_id = p_tenant_id
                    AND ac.deleted_at IS NULL
                    AND ac.isactive = TRUE;

                IF category_count = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No matching categories found'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS category_name,
                        NULL::TEXT AS category_description,
                        NULL::JSON AS reading_parameters,
                        NULL::BIGINT AS asset_type;

                    RETURN;
                END IF;

                -- Return the matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Categories fetched successfully'::TEXT AS message,
                    ac.id,
                    ac.name::TEXT AS category_name,
                    ac.description::TEXT AS category_description,
                    ac.reading_parameters::JSON,
                    ac.assets_type::BIGINT
                FROM
                    asset_categories ac
                WHERE
                    (p_asset_categories_id IS NULL OR ac.id = p_asset_categories_id)
                    AND ac.tenant_id = p_tenant_id
                    AND ac.deleted_at IS NULL
                    AND ac.isactive = TRUE;
            END;
        $$;
        SQL);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       DB::unprepared(<<<SQL

        DO $$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_all_asset_categories'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
