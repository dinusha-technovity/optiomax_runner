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
            CREATE OR REPLACE FUNCTION update_procurement_staff(
                IN p_staff_id BIGINT,
                IN p_user_id BIGINT,
                IN p_asset_type_id BIGINT,
                IN p_tenant_id BIGINT,
                IN p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                before_data JSONB,
                after_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT; -- Variable to capture affected rows
                data_before JSONB; -- Variable to store data before the update
                data_after JSONB;  -- Variable to store data after the update
                existing_added_user_count INT;
            BEGIN
                -- Check if the user is already assigned to the asset type
                SELECT COUNT(*) INTO existing_added_user_count
                FROM procurement_staff
                WHERE user_id = p_user_id
                AND asset_type_id = p_asset_type_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- If references exist, return failure message
                IF existing_added_user_count > 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'This user is already assigned to this asset type'::TEXT AS message,
                        NULL::JSONB AS before_data,
                        NULL::JSONB AS after_data;
                    RETURN; -- Exit early
                END IF;

                -- Fetch the asset item data before update
                SELECT to_jsonb(procurement_staff) INTO data_before
                FROM procurement_staff
                WHERE id = p_staff_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                LIMIT 1;

                -- Update the procurement_staff table
                UPDATE procurement_staff
                SET 
                    user_id = p_user_id,
                    asset_type_id = p_asset_type_id,
                    updated_at = p_current_time
                WHERE id = p_staff_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Fetch data after the update if rows were updated
                IF rows_updated > 0 THEN
                    SELECT to_jsonb(procurement_staff) INTO data_after
                    FROM procurement_staff
                    WHERE id = p_staff_id
                    LIMIT 1;

                    -- Return success with before and after data
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Procurement staff updated successfully'::TEXT AS message,
                        data_before,
                        data_after;
                ELSE
                    -- Return failure message with before data and null after data
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Procurement staff not found.'::TEXT AS message,
                        data_before,
                        NULL::JSONB AS after_data;
                END IF;
            END;
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS update_procurement_staff');
    }
};
