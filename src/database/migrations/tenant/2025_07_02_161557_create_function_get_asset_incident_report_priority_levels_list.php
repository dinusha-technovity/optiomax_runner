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
            CREATE OR REPLACE FUNCTION get_asset_incident_report_priority_levels_list(
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_priority_level_id INT DEFAULT NULL
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT,
                id BIGINT,
                value TEXT,
                label TEXT,
                color TEXT
            )
            LANGUAGE plpgsql
            AS \$\$
            BEGIN
                IF p_tenant_id IS NULL AND p_priority_level_id IS NULL THEN
                    RETURN QUERY
                    SELECT
                        'SUCCESS'::TEXT,
                        'All priority levels fetched successfully'::TEXT,
                        pl.id,
                        pl.value::TEXT,
                        pl.label::TEXT,
                        pl.color::TEXT
                    FROM asset_maintenance_incident_report_priority_levels pl
                    WHERE pl.deleted_at IS NULL
                    AND pl.isactive = TRUE;
                    RETURN;
                END IF;

                IF p_tenant_id IS NULL OR p_tenant_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT,
                        'Invalid tenant ID provided'::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::TEXT,
                        NULL::TEXT;
                    RETURN;
                END IF;

                IF p_priority_level_id IS NOT NULL AND p_priority_level_id < 0 THEN
                    RETURN QUERY 
                    SELECT 
                        'FAILURE'::TEXT,
                        'Invalid priority level ID provided'::TEXT,
                        NULL::BIGINT,
                        NULL::TEXT,
                        NULL::TEXT,
                        NULL::TEXT;
                    RETURN;
                END IF;

                RETURN QUERY
                SELECT
                    'SUCCESS'::TEXT,
                    'Priority levels fetched successfully'::TEXT,
                    pl.id,
                    pl.value::TEXT,
                    pl.label::TEXT,
                    pl.color::TEXT
                FROM asset_maintenance_incident_report_priority_levels pl
                WHERE (p_priority_level_id IS NULL OR pl.id = p_priority_level_id)
                AND pl.tenant_id = p_tenant_id
                AND pl.deleted_at IS NULL
                AND pl.isactive = TRUE;
            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_asset_incident_report_priority_levels_list');
    }
};
