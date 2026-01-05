<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<SQL
        DROP FUNCTION IF EXISTS delete_designation(BIGINT, BIGINT, BIGINT);
        DROP FUNCTION IF EXISTS delete_designation(BIGINT, BIGINT);

        CREATE OR REPLACE FUNCTION delete_designation(
            IN p_designation_id BIGINT,
            IN p_tenant_id BIGINT,
            IN p_action_by BIGINT
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT
        )
        LANGUAGE plpgsql
        AS \$\$
        BEGIN
            -- Validate required fields
            IF p_designation_id IS NULL OR p_designation_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid designation ID'::TEXT;
                RETURN;
            END IF;

            IF p_tenant_id IS NULL OR p_tenant_id <= 0 THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Invalid tenant ID'::TEXT;
                RETURN;
            END IF;

            -- Check if designation exists and belongs to tenant
            IF NOT EXISTS (
                SELECT 1 FROM designations 
                WHERE id = p_designation_id 
                AND tenant_id = p_tenant_id 
                AND deleted_at IS NULL
            ) THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, 'Designation not found or access denied'::TEXT;
                RETURN;
            END IF;

            -- Soft delete designation record
            UPDATE designations 
            SET 
                deleted_at = NOW(),
                isactive = FALSE,
                updated_at = NOW()
            WHERE id = p_designation_id AND tenant_id = p_tenant_id;

            RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Designation deleted successfully'::TEXT;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY SELECT 'FAILURE'::TEXT, ('Error: ' || SQLERRM)::TEXT;
        END;
        \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS delete_designation(BIGINT, BIGINT, BIGINT)');
    }
};
