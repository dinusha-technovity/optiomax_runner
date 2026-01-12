<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add database constraints and validation rules for data integrity
     */
    public function up(): void
    {
        // Add check constraints for score validations
        DB::statement("
            ALTER TABLE asset_items_audit_score
            ADD CONSTRAINT chk_final_score_range 
            CHECK (final_score IS NULL OR (final_score >= 0 AND final_score <= 100));
        ");

        DB::statement("
            ALTER TABLE asset_items_audit_score
            ADD CONSTRAINT chk_physical_score_range 
            CHECK (physical_condition_score IS NULL OR (physical_condition_score >= 0 AND physical_condition_score <= 100));
        ");

        DB::statement("
            ALTER TABLE asset_items_audit_score
            ADD CONSTRAINT chk_operational_score_range 
            CHECK (system_or_operational_condition_score IS NULL OR (system_or_operational_condition_score >= 0 AND system_or_operational_condition_score <= 100));
        ");

        DB::statement("
            ALTER TABLE asset_items_audit_score
            ADD CONSTRAINT chk_compliance_score_range 
            CHECK (compliance_and_usage_score IS NULL OR (compliance_and_usage_score >= 0 AND compliance_and_usage_score <= 100));
        ");

        DB::statement("
            ALTER TABLE asset_items_audit_score
            ADD CONSTRAINT chk_risk_score_range 
            CHECK (risk_and_replacement_need_score IS NULL OR (risk_and_replacement_need_score >= 0 AND risk_and_replacement_need_score <= 100));
        ");

        // Add trigger to automatically update timestamps on related tables
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION update_audit_session_timestamp()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE asset_items_audit_sessions 
                SET updated_at = NOW() 
                WHERE id = NEW.asset_items_audit_sessions_id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
SQL
        );

        DB::statement("
            CREATE TRIGGER trg_update_session_on_record_change
            AFTER INSERT OR UPDATE ON asset_items_audited_record
            FOR EACH ROW
            EXECUTE FUNCTION update_audit_session_timestamp()
        ");

        DB::statement("
            CREATE TRIGGER trg_update_session_on_score_change
            AFTER INSERT OR UPDATE ON asset_items_audit_score
            FOR EACH ROW
            EXECUTE FUNCTION update_audit_session_timestamp()
        ");

        // Add function to validate audit session completeness
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION validate_audit_session_completeness(
                p_session_id BIGINT,
                p_tenant_id BIGINT
            )
            RETURNS TABLE (
                is_complete BOOLEAN,
                total_variables INT,
                completed_variables INT,
                missing_variables TEXT[],
                has_score BOOLEAN,
                message TEXT
            ) AS $$
            DECLARE
                v_asset_item_id BIGINT;
                v_expected_variables BIGINT[];
                v_recorded_variables BIGINT[];
                v_missing BIGINT[];
                v_has_score BOOLEAN;
            BEGIN
                -- Get asset item ID for this session
                SELECT asset_item_id INTO v_asset_item_id
                FROM asset_items_audit_sessions
                WHERE id = p_session_id AND tenant_id = p_tenant_id;

                IF v_asset_item_id IS NULL THEN
                    RETURN QUERY SELECT 
                        FALSE, 0, 0, ARRAY[]::TEXT[], FALSE, 
                        'Audit session not found'::TEXT;
                    RETURN;
                END IF;

                -- Get expected audit variables for this asset item
                SELECT ARRAY_AGG(asset_audit_variable_id)
                INTO v_expected_variables
                FROM asset_audit_variable_assignments
                WHERE (assignable_type_id, assignable_id) IN (
                    SELECT id as type_id, v_asset_item_id as item_id
                    FROM assignable_types WHERE name = 'AssetItem'
                    UNION
                    SELECT at.id, ai.asset_id
                    FROM asset_items ai
                    JOIN assignable_types at ON at.name = 'Asset'
                    WHERE ai.id = v_asset_item_id
                )
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL
                AND is_active = TRUE;

                -- Get recorded variables
                SELECT ARRAY_AGG(asset_audit_variable_id)
                INTO v_recorded_variables
                FROM asset_items_audited_record
                WHERE asset_items_audit_sessions_id = p_session_id
                AND deleted_at IS NULL;

                -- Check if score exists
                SELECT EXISTS(
                    SELECT 1 FROM asset_items_audit_score
                    WHERE asset_items_audit_sessions_id = p_session_id
                    AND deleted_at IS NULL
                ) INTO v_has_score;

                -- Calculate missing variables
                v_missing := ARRAY(
                    SELECT UNNEST(v_expected_variables)
                    EXCEPT
                    SELECT UNNEST(v_recorded_variables)
                );

                -- Return results
                RETURN QUERY SELECT
                    (ARRAY_LENGTH(v_missing, 1) IS NULL OR ARRAY_LENGTH(v_missing, 1) = 0) AND v_has_score,
                    COALESCE(ARRAY_LENGTH(v_expected_variables, 1), 0),
                    COALESCE(ARRAY_LENGTH(v_recorded_variables, 1), 0),
                    ARRAY(
                        SELECT name::TEXT 
                        FROM asset_audit_variable 
                        WHERE id = ANY(v_missing)
                    ),
                    v_has_score,
                    CASE 
                        WHEN ARRAY_LENGTH(v_missing, 1) IS NULL OR ARRAY_LENGTH(v_missing, 1) = 0 THEN
                            CASE WHEN v_has_score THEN 'Audit session is complete' 
                                 ELSE 'Missing final scores' 
                            END
                        ELSE 'Missing audit variable records'
                    END;
            END;
            $$ LANGUAGE plpgsql
SQL
        );

        DB::statement("
            COMMENT ON FUNCTION validate_audit_session_completeness IS 
            'Validates if an audit session has all required variable records and scores'
        ");

        // Add function to calculate audit completion percentage per tenant
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION get_tenant_audit_completion_rate(
                p_tenant_id BIGINT,
                p_date_from TIMESTAMP DEFAULT NULL,
                p_date_to TIMESTAMP DEFAULT NULL
            )
            RETURNS TABLE (
                total_sessions BIGINT,
                complete_sessions BIGINT,
                incomplete_sessions BIGINT,
                completion_rate DECIMAL(5,2),
                avg_variables_per_session DECIMAL(10,2),
                avg_completion_time_hours DECIMAL(10,2)
            ) AS $$
            BEGIN
                RETURN QUERY
                WITH session_stats AS (
                    SELECT 
                        aias.id,
                        aias.created_at,
                        aias.updated_at,
                        COUNT(aiar.id) as recorded_variables,
                        EXISTS(
                            SELECT 1 FROM asset_items_audit_score aias_score
                            WHERE aias_score.asset_items_audit_sessions_id = aias.id
                            AND aias_score.deleted_at IS NULL
                        ) as has_score,
                        EXTRACT(EPOCH FROM (aias.updated_at - aias.created_at))/3600 as hours_taken
                    FROM asset_items_audit_sessions aias
                    LEFT JOIN asset_items_audited_record aiar 
                        ON aiar.asset_items_audit_sessions_id = aias.id
                        AND aiar.deleted_at IS NULL
                    WHERE aias.tenant_id = p_tenant_id
                        AND aias.deleted_at IS NULL
                        AND (p_date_from IS NULL OR aias.created_at >= p_date_from)
                        AND (p_date_to IS NULL OR aias.created_at <= p_date_to)
                    GROUP BY aias.id, aias.created_at, aias.updated_at
                )
                SELECT
                    COUNT(*)::BIGINT as total_sessions,
                    COUNT(*) FILTER (WHERE has_score AND recorded_variables > 0)::BIGINT as complete_sessions,
                    COUNT(*) FILTER (WHERE NOT has_score OR recorded_variables = 0)::BIGINT as incomplete_sessions,
                    ROUND(
                        (COUNT(*) FILTER (WHERE has_score AND recorded_variables > 0)::DECIMAL / 
                         NULLIF(COUNT(*)::DECIMAL, 0)) * 100, 
                        2
                    ) as completion_rate,
                    ROUND(AVG(recorded_variables), 2) as avg_variables_per_session,
                    ROUND(AVG(hours_taken), 2) as avg_completion_time_hours
                FROM session_stats;
            END;
            $$ LANGUAGE plpgsql
SQL
        );

        DB::statement("
            COMMENT ON FUNCTION get_tenant_audit_completion_rate IS 
            'Calculate audit completion statistics for a tenant within a date range'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS get_tenant_audit_completion_rate(BIGINT, TIMESTAMP, TIMESTAMP)');
        DB::statement('DROP FUNCTION IF EXISTS validate_audit_session_completeness(BIGINT, BIGINT)');
        
        DB::statement('DROP TRIGGER IF EXISTS trg_update_session_on_score_change ON asset_items_audit_score');
        DB::statement('DROP TRIGGER IF EXISTS trg_update_session_on_record_change ON asset_items_audited_record');
        DB::statement('DROP FUNCTION IF EXISTS update_audit_session_timestamp()');

        DB::statement('ALTER TABLE asset_items_audit_score DROP CONSTRAINT IF EXISTS chk_risk_score_range');
        DB::statement('ALTER TABLE asset_items_audit_score DROP CONSTRAINT IF EXISTS chk_compliance_score_range');
        DB::statement('ALTER TABLE asset_items_audit_score DROP CONSTRAINT IF EXISTS chk_operational_score_range');
        DB::statement('ALTER TABLE asset_items_audit_score DROP CONSTRAINT IF EXISTS chk_physical_score_range');
        DB::statement('ALTER TABLE asset_items_audit_score DROP CONSTRAINT IF EXISTS chk_final_score_range');
    }
};