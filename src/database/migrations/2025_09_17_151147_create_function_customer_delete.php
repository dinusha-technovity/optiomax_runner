<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION delete_customer(
                p_customer_id BIGINT,
                p_tenant_id BIGINT,
                p_user_id BIGINT,
                p_current_time TIMESTAMPTZ
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT
            )
            LANGUAGE plpgsql
            AS $$
            DECLARE
                rows_updated INT;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
                v_customer_exists BOOLEAN;
            BEGIN
                -- Check if record exists and is not already deleted
                SELECT TRUE INTO v_customer_exists
                FROM customers
                WHERE id = p_customer_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                LIMIT 1;

                IF NOT FOUND THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'Customer not found or already deleted'::TEXT AS message;
                    RETURN;
                END IF;

                -- Soft delete the customer
                UPDATE customers
                SET deleted_at = p_current_time,
                    is_active = FALSE
                WHERE id = p_customer_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;

                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                IF rows_updated > 0 THEN
                    -- Build log data
                    v_log_data := jsonb_build_object(
                        'customer_id', p_customer_id,
                        'deleted_at', p_current_time,
                        'tenant_id', p_tenant_id,
                        'deleted_by', p_user_id
                    );

                    -- Log activity if user info provided
                    IF p_user_id IS NOT NULL THEN
                        BEGIN
                            PERFORM log_activity(
                                'customer.deleted',
                                'Customer deleted: ' || p_customer_id,
                                'customer',
                                p_customer_id,
                                'user',
                                p_user_id,
                                v_log_data,
                                p_tenant_id
                            );
                            v_log_success := TRUE;
                        EXCEPTION WHEN OTHERS THEN
                            v_log_success := FALSE;
                            v_error_message := 'Logging failed: ' || SQLERRM;
                        END;
                    END IF;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status,
                        'Customer deleted successfully'::TEXT AS message;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status,
                        'No rows updated. Customer not found or already deleted.'::TEXT AS message;
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
        DB::unprepared("DROP FUNCTION IF EXISTS delete_customer(BIGINT, BIGINT, BIGINT, TIMESTAMPTZ);");
    }
};
