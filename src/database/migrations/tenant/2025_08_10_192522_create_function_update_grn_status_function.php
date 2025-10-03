<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION update_grn_status_function(
            IN p_grn_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_status TEXT,
            IN p_user_id BIGINT DEFAULT NULL,
            IN p_user_name VARCHAR DEFAULT NULL,
            IN p_current_time TIMESTAMPTZ DEFAULT now()
        ) RETURNS TABLE (
            status TEXT,
            message TEXT
        ) LANGUAGE plpgsql AS $$
        DECLARE
            v_old_status TEXT;
            v_grn_number TEXT;
            v_log_data JSONB;
        BEGIN
            -- Validate tenant_id
            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE', 'Invalid tenant ID provided';
                RETURN;
            END IF;

            -- Validate status value
            IF p_status NOT IN ('draft', 'posted', 'cancelled') THEN
                RETURN QUERY SELECT 'FAILURE', 'Invalid status value';
                RETURN;
            END IF;

            -- Check if GRN exists for tenant
            SELECT g.status, g.grn_number
            INTO v_old_status, v_grn_number
            FROM goods_received_note g
            WHERE g.id = p_grn_id
            AND g.tenant_id = p_tenant_id;

            IF v_old_status IS NULL THEN
                RETURN QUERY SELECT 'FAILURE', 'GRN not found';
                RETURN;
            END IF;

            -- Update status
            UPDATE goods_received_note g
            SET status = p_status,
                updated_at = p_current_time
            WHERE g.id = p_grn_id
            AND g.tenant_id = p_tenant_id;

            -- Build log data
            v_log_data := jsonb_build_object(
                'grn_id', p_grn_id,
                'old_status', v_old_status,
                'new_status', p_status
            );

            -- Optional logging
            IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                BEGIN
                    PERFORM log_activity(
                        'grn.status_updated',
                        'GRN status changed from ' || v_old_status || ' to ' || p_status || ' by ' || p_user_name || ': ' || v_grn_number,
                        'grn',
                        p_grn_id,
                        'user',
                        p_user_id,
                        v_log_data,
                        p_tenant_id
                    );
                EXCEPTION WHEN OTHERS THEN
                    -- Ignore logging errors
                END;
            END IF;

            RETURN QUERY SELECT 'SUCCESS', 'GRN status updated successfully';
        END;
        $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS update_grn_status_function(BIGINT, BIGINT, TEXT, BIGINT, VARCHAR, TIMESTAMPTZ)');
    }
};