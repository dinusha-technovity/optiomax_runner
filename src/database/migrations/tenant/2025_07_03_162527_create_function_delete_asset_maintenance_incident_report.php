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
            CREATE OR REPLACE FUNCTION delete_asset_maintenance_incident_report(
                p_report_id BIGINT,
                p_tenant_id BIGINT,
                p_current_time TIMESTAMP WITH TIME ZONE,
                p_causer_id BIGINT,
                p_causer_name TEXT
            )
            RETURNS TABLE (
                status TEXT,
                message TEXT
            )
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                rows_updated INT;
                deleted_row JSONB;
            BEGIN
                -- Soft delete with row capture
                UPDATE asset_maintenance_incident_reports
                SET deleted_at = p_current_time
                WHERE id = p_report_id
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                RETURNING to_jsonb(asset_maintenance_incident_reports) INTO deleted_row;

                GET DIAGNOSTICS rows_updated = ROW_COUNT;

                IF rows_updated > 0 THEN
                    BEGIN
                        PERFORM log_activity(
                            'delete_incident_report',
                            format('Deleted incident report ID %s by %s', p_report_id, p_causer_name),
                            'asset_maintenance_incident_reports',
                            p_report_id,
                            'user',
                            p_causer_id,
                            deleted_row,
                            p_tenant_id
                        );
                    EXCEPTION WHEN OTHERS THEN NULL;
                    END;

                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT AS status, 
                        'Incident Report deleted successfully'::TEXT AS message;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT AS status, 
                        'No rows updated. Incident Report not found or already deleted.'::TEXT AS message;
                END IF;
            END;
            \$\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_delete_asset_maintenance_incident_report');
    }
};
