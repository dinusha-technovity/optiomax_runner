<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_document_media(
            p_document_media_id BIGINT,
            p_tenant_id BIGINT,
            p_current_time TIMESTAMP WITH TIME ZONE,
            p_user_id BIGINT,
            p_user_name VARCHAR
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            rows_updated INT;
            v_deleted_data JSONB;
            v_log_success BOOLEAN;
        BEGIN
            -- Get old document media data for logging
            SELECT to_jsonb(document_media) INTO v_deleted_data
            FROM document_media
            WHERE id = p_document_media_id
            AND tenant_id = p_tenant_id
            AND deleted_at IS NULL;

            -- Soft delete the document media
            UPDATE document_media
            SET 
                deleted_at = p_current_time,
                modified_by = p_user_id,
                isactive = FALSE
            WHERE id = p_document_media_id
            AND tenant_id = p_tenant_id
            AND deleted_at IS NULL;

            -- Log the activity
            BEGIN
                PERFORM log_activity(
                    'document_media.soft_delete',
                    'Document Media deleted by ' || p_user_name,
                    'document_media',
                    p_document_media_id,
                    'user',
                    p_user_id,
                    v_deleted_data,
                    p_tenant_id
                );
                v_log_success := TRUE;
            EXCEPTION WHEN OTHERS THEN
                v_log_success := FALSE;
            END;

            -- Get number of rows affected
            GET DIAGNOSTICS rows_updated = ROW_COUNT;

            IF rows_updated > 0 THEN
                RETURN QUERY SELECT 
                    'SUCCESS'::TEXT AS status, 
                    'Document media deleted successfully'::TEXT AS message;
            ELSE
                RETURN QUERY SELECT 
                    'FAILURE'::TEXT AS status, 
                    'No rows updated. Document media not found or already deleted.'::TEXT AS message;
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
         DB::unprepared('DROP FUNCTION IF EXISTS delete_document_media(BIGINT, BIGINT, TIMESTAMP WITH TIME ZONE, BIGINT, VARCHAR);');
    }
};
