<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
 
return new class extends Migration
{ 
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_procurement_staff(
                p_procurement_staff_id BIGINT,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                deleted_data JSONB
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;          -- Tracks the number of affected rows
                deleted_data JSONB;        -- Holds the data of the deleted procurement staff
            BEGIN
                -- Check if the procurement staff ID is valid
                IF p_procurement_staff_id IS NULL OR p_procurement_staff_id = 0 THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Procurement ID cannot be null or zero'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Check if the procurement staff exists
                SELECT to_jsonb(procurement_staff) INTO deleted_data
                FROM procurement_staff
                WHERE id = p_procurement_staff_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                IF deleted_data IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'Procurement ID does not exist or is already deleted'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                    RETURN;
                END IF;

                -- Mark the procurement staff as deleted
                UPDATE procurement_staff
                SET deleted_at = p_current_time, isactive = FALSE
                WHERE id = p_procurement_staff_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                -- Capture the number of rows updated
                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                -- Return appropriate message based on the update result
                IF rows_updated > 0 THEN
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Procurement staff deleted successfully'::TEXT AS message,
                        deleted_data;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Procurement staff not found or already deleted.'::TEXT AS message,
                        NULL::JSONB AS deleted_data;
                END IF;
            END;
            $$;
        SQL);

    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_procurement_staff');
    }
};