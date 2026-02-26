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
            CREATE OR REPLACE FUNCTION update_audit_group_details(
                p_audit_group_id BIGINT,
                p_tenant_id BIGINT,
                p_update_data JSONB,
                p_user_id BIGINT,
                p_user_name TEXT,
                p_current_time TIMESTAMP WITH TIME ZONE
            )
            RETURNS TABLE (
                status TEXT, 
                message TEXT,
                before_data JSONB,
                after_data JSONB,
                activity_log_id BIGINT
            )
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                rows_updated INT;
                data_before JSONB;
                data_after JSONB;
                query TEXT;
                key TEXT;
                value TEXT;
                first BOOLEAN := TRUE;
                new_name TEXT;
                existing_group_count INTEGER;
                log_id BIGINT;
                changed_fields TEXT[];
            BEGIN
                -- Check if updating name
                IF p_update_data ? 'name' THEN
                    new_name := p_update_data->>'name';
                    
                    -- Validate name is not empty
                    IF new_name IS NULL OR LENGTH(TRIM(new_name)) = 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT,
                            'Audit group name cannot be empty'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            NULL::BIGINT;
                        RETURN;
                    END IF;
                    
                    -- Check for duplicate name in the same tenant
                    SELECT COUNT(*) INTO existing_group_count
                    FROM audit_groups
                    WHERE LOWER(TRIM(name)) = LOWER(TRIM(new_name))
                    AND tenant_id = p_tenant_id
                    AND id != p_audit_group_id
                    AND deleted_at IS NULL;

                    IF existing_group_count > 0 THEN
                        RETURN QUERY SELECT 
                            'FAILURE'::TEXT, 
                            'Audit group name already exists for this tenant'::TEXT,
                            NULL::JSONB,
                            NULL::JSONB,
                            NULL::BIGINT;
                        RETURN;
                    END IF;
                END IF;

                -- Capture before data
                SELECT row_to_json(ag.*) INTO data_before
                FROM audit_groups ag
                WHERE id = p_audit_group_id 
                AND tenant_id = p_tenant_id
                AND deleted_at IS NULL;
                
                -- Check if record exists
                IF data_before IS NULL THEN
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'Audit group not found or tenant mismatch'::TEXT,
                        NULL::JSONB,
                        NULL::JSONB,
                        NULL::BIGINT;
                    RETURN;
                END IF;
                
                -- Build dynamic update query
                query := 'UPDATE audit_groups SET ';
                
                FOR key, value IN SELECT * FROM jsonb_each_text(p_update_data) LOOP
                    IF NOT first THEN
                        query := query || ', ';
                    ELSE
                        first := FALSE;
                    END IF;
                    changed_fields := array_append(changed_fields, key);
                    query := query || format('%I = %L', key, value);
                END LOOP;
                
                query := query || format(', updated_at = %L WHERE id = %L AND tenant_id = %L AND deleted_at IS NULL', 
                                        p_current_time, p_audit_group_id, p_tenant_id);
                
                -- Execute update
                EXECUTE query;
                
                GET DIAGNOSTICS rows_updated = ROW_COUNT;
                
                IF rows_updated > 0 THEN
                    -- Capture after data
                    SELECT row_to_json(ag.*) INTO data_after
                    FROM audit_groups ag
                    WHERE id = p_audit_group_id AND tenant_id = p_tenant_id;
                    
                    -- Create activity log entry
                    INSERT INTO activity_log (
                        log_name,
                        description,
                        subject_type,
                        subject_id,
                        causer_type,
                        causer_id,
                        properties,
                        created_at,
                        updated_at
                    ) VALUES (
                        'audit_group',
                        format('Updated audit group "%s"', (data_after->>'name')::TEXT),
                        'App\\Models\\AuditGroup',
                        p_audit_group_id,
                        'App\\Models\\User',
                        p_user_id,
                        jsonb_build_object(
                            'action', 'update',
                            'before', data_before,
                            'after', data_after,
                            'changed_fields', changed_fields,
                            'user_name', p_user_name,
                            'tenant_id', p_tenant_id
                        ),
                        p_current_time,
                        p_current_time
                    ) RETURNING id INTO log_id;
                    
                    RETURN QUERY SELECT 
                        'SUCCESS'::TEXT, 
                        'Audit group updated successfully'::TEXT,
                        data_before,
                        data_after,
                        log_id;
                ELSE
                    RETURN QUERY SELECT 
                        'FAILURE'::TEXT, 
                        'No rows updated. Audit group not found or tenant mismatch.'::TEXT,
                        data_before,
                        NULL::JSONB,
                        NULL::BIGINT;
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
        DB::unprepared(<<<SQL
            DROP FUNCTION IF EXISTS update_audit_group_details(
                BIGINT,
                BIGINT,
                JSONB,
                BIGINT,
                TEXT,
                TIMESTAMP WITH TIME ZONE
            );
        SQL);
    }
};
