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
        // Create the PostgreSQL function
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION get_all_asset_booking_cancelling_fee_types(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_fee_type_id BIGINT DEFAULT NULL
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
                IF p_tenant_id IS NULL AND p_fee_type_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT AS status,
                        'All asset booking cancelling fee types fetched successfully'::TEXT AS message,
                        a.id,
                        a.name::TEXT,
                        a.description::TEXT
                    FROM asset_booking_cancelling_fee_types a
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

                -- Validate fee type ID (optional)
                IF p_fee_type_id IS NOT NULL AND p_fee_type_id < 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Invalid fee type ID provided'::TEXT AS message,
                        NULL::BIGINT AS id,
                        NULL::TEXT AS name,
                        NULL::TEXT AS description;
                    RETURN;
                END IF;

                -- Return matching records
                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT AS status,
                    'Asset booking cancelling fee types fetched successfully'::TEXT AS message,
                    a.id,
                    a.name::TEXT,
                    a.description::TEXT 
                FROM
                    asset_booking_cancelling_fee_types a
                WHERE (p_fee_type_id IS NULL OR a.id = p_fee_type_id)
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
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_asset_availability_term_types');
    }
};
