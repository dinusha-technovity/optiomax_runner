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
        CREATE OR REPLACE FUNCTION delete_procurement_staff_by_user(
            IN p_user_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_current_time TIMESTAMPTZ,
            IN p_causer_id BIGINT DEFAULT NULL,
            IN p_causer_name TEXT DEFAULT NULL
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            before_data JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            deleted_row JSONB;
            result_before JSONB := '[]';
            user_exists BOOLEAN;
        BEGIN
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Tenant ID must be valid', NULL::JSONB;
                RETURN;
            END IF;

            SELECT EXISTS(
                SELECT 1 FROM users WHERE id = p_user_id AND deleted_at IS NULL AND tenant_id = p_tenant_id
            ) INTO user_exists;

            IF NOT user_exists THEN
                RETURN QUERY SELECT 'FAILURE', 'Invalid user_id. User not found.', NULL::JSONB;
                RETURN;
            END IF;

            FOR deleted_row IN
                UPDATE procurement_staff
                SET deleted_at = p_current_time
                WHERE user_id = p_user_id AND tenant_id = p_tenant_id AND deleted_at IS NULL
                RETURNING to_jsonb(procurement_staff)
            LOOP
                result_before := result_before || jsonb_build_array(deleted_row);

                BEGIN
                    PERFORM log_activity(
                        'delete_procurement_staff',
                        format('User %s removed all procurement_staff entries for user_id=%s', p_causer_name, p_user_id),
                        'procurement_staff',
                        (deleted_row->>'id')::BIGINT,
                        'user',
                        p_causer_id,
                        deleted_row,
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN NULL; END;
            END LOOP;

            IF jsonb_array_length(result_before) > 0 THEN
                RETURN QUERY SELECT 'SUCCESS', 'All procurement staff entries deleted.', result_before;
            ELSE
                RETURN QUERY SELECT 'FAILURE', 'No matching records found to delete.', NULL::JSONB;
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
        DB::unprepared('DROP FUNCTION IF EXISTS delete_procurement_staff_by_user(BIGINT, BIGINT, TIMESTAMPTZ, BIGINT, TEXT);');
    }
};
