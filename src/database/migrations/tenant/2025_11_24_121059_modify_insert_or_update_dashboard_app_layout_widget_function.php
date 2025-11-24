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
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                -- Drop all existing versions of the function
                FOR r IN
                    SELECT oid::regprocedure::text AS func_signature
                    FROM pg_proc
                    WHERE proname = 'insert_or_update_dashboard_app_layout_widget'
                LOOP
                    EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
                END LOOP;
            END$$;
            
            CREATE OR REPLACE FUNCTION insert_or_update_dashboard_app_layout_widget(
                IN p_x DOUBLE PRECISION,
                IN p_y DOUBLE PRECISION,
                IN p_w DOUBLE PRECISION,
                IN p_h DOUBLE PRECISION,
                IN p_style TEXT,
                IN p_widget_id BIGINT,
                IN p_widget_type TEXT,
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_user_name VARCHAR DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_is_active BOOLEAN DEFAULT TRUE,
                IN p_current_time TIMESTAMPTZ DEFAULT now(),
                IN p_id BIGINT DEFAULT NULL
            ) RETURNS TABLE (
                status TEXT,
                message TEXT,
                widget_id BIGINT
            )
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                return_layout_id BIGINT;
                existing_widget_id BIGINT;
                v_new_data JSONB;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
            BEGIN
                -- Case 1: Insert new
                IF p_id IS NULL OR p_id = 0 THEN
                    -- Check if a widget already exists for this tenant_id, user_id, and widget_id
                    SELECT alw.id
                    INTO existing_widget_id
                    FROM app_layout_widgets alw
                    WHERE alw.tenant_id = p_tenant_id
                    AND alw.user_id = p_user_id
                    AND alw.widget_id = p_widget_id
                    AND alw.deleted_at IS NULL
                    LIMIT 1;

                    -- If widget already exists, return error
                    IF existing_widget_id IS NOT NULL THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT, 
                            'This widget already added to your dashboard'::TEXT, 
                            existing_widget_id;
                        RETURN;
                    END IF;

                    -- Check for duplicate with same position and style
                    SELECT alw.id
                    INTO return_layout_id
                    FROM app_layout_widgets alw
                    WHERE alw.x = p_x 
                    AND alw.y = p_y 
                    AND alw.w = p_w 
                    AND alw.h = p_h 
                    AND alw.style = p_style
                    AND alw.user_id = p_user_id
                    AND alw.widget_id = p_widget_id
                    AND alw.widget_type = p_widget_type
                    AND alw.tenant_id = p_tenant_id
                    AND alw.deleted_at IS NULL;

                    IF NOT FOUND THEN
                        INSERT INTO app_layout_widgets (
                            x, y, w, h, style, widget_id, widget_type,
                            user_id, tenant_id, is_active,
                            created_at, updated_at
                        ) VALUES (
                            p_x, p_y, p_w, p_h, p_style, p_widget_id, p_widget_type,
                            p_user_id, p_tenant_id, p_is_active,
                            p_current_time, p_current_time
                        )
                        RETURNING id INTO return_layout_id;

                        -- Build new snapshot
                        v_new_data := jsonb_build_object(
                            'id', return_layout_id,
                            'x', p_x,
                            'y', p_y,
                            'w', p_w,
                            'h', p_h,
                            'style', p_style,
                            'widget_id', p_widget_id,
                            'widget_type', p_widget_type,
                            'user_id', p_user_id,
                            'tenant_id', p_tenant_id,
                            'is_active', p_is_active,
                            'created_at', p_current_time
                        );
                        v_log_data := jsonb_build_object('widget_id', return_layout_id, 'new_data', v_new_data);

                        -- Log
                        IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                PERFORM log_activity(
                                    'widget.created',
                                    'Widget created by ' || p_user_name,
                                    'app_layout_widget',
                                    return_layout_id,
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

                        RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Widget added successfully'::TEXT, return_layout_id;
                    ELSE
                        UPDATE app_layout_widgets alw
                        SET updated_at = p_current_time
                        WHERE alw.id = return_layout_id;

                        RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Widget already exists and was updated'::TEXT, return_layout_id;
                    END IF;

                -- Case 2: Update existing
                ELSE
                    -- Check if another widget exists with same tenant_id, user_id, widget_id (excluding current record)
                    SELECT alw.id
                    INTO existing_widget_id
                    FROM app_layout_widgets alw
                    WHERE alw.tenant_id = p_tenant_id
                    AND alw.user_id = p_user_id
                    AND alw.widget_id = p_widget_id
                    AND alw.id != p_id
                    AND alw.deleted_at IS NULL
                    LIMIT 1;

                    -- If another widget exists, return error
                    IF existing_widget_id IS NOT NULL THEN
                        RETURN QUERY SELECT 
                            'ERROR'::TEXT, 
                            'Another widget already exists for this tenant, user, and widget combination'::TEXT, 
                            existing_widget_id;
                        RETURN;
                    END IF;

                    UPDATE app_layout_widgets alw
                    SET x = p_x,
                        y = p_y,
                        w = p_w,
                        h = p_h,
                        style = p_style,
                        widget_id = p_widget_id,
                        widget_type = p_widget_type,
                        user_id = p_user_id,
                        tenant_id = p_tenant_id,
                        is_active = p_is_active,
                        updated_at = p_current_time
                    WHERE alw.id = p_id
                    RETURNING alw.id INTO return_layout_id;

                    IF FOUND THEN
                        -- Build new snapshot
                        v_new_data := jsonb_build_object(
                            'id', return_layout_id,
                            'x', p_x,
                            'y', p_y,
                            'w', p_w,
                            'h', p_h,
                            'style', p_style,
                            'widget_id', p_widget_id,
                            'widget_type', p_widget_type,
                            'user_id', p_user_id,
                            'tenant_id', p_tenant_id,
                            'is_active', p_is_active,
                            'updated_at', p_current_time
                        );
                        v_log_data := jsonb_build_object('widget_id', return_layout_id, 'new_data', v_new_data);

                        -- Log
                        IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                PERFORM log_activity(
                                    'widget.updated',
                                    'Widget updated by ' || p_user_name,
                                    'app_layout_widget',
                                    return_layout_id,
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

                        RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Widget updated successfully'::TEXT, return_layout_id;
                    ELSE
                        INSERT INTO app_layout_widgets (
                            x, y, w, h, style, widget_id, widget_type,
                            user_id, tenant_id, is_active,
                            created_at, updated_at
                        ) VALUES (
                            p_x, p_y, p_w, p_h, p_style, p_widget_id, p_widget_type,
                            p_user_id, p_tenant_id, p_is_active,
                            p_current_time, p_current_time
                        )
                        RETURNING id INTO return_layout_id;

                        RETURN QUERY SELECT 'SUCCESS'::TEXT, 'Widget added successfully'::TEXT, return_layout_id;
                    END IF;
                END IF;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT 'ERROR'::TEXT, SQLERRM, NULL::BIGINT;
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
            CREATE OR REPLACE FUNCTION insert_or_update_dashboard_app_layout_widget(
                IN p_x DOUBLE PRECISION,
                IN p_y DOUBLE PRECISION,
                IN p_w DOUBLE PRECISION,
                IN p_h DOUBLE PRECISION,
                IN p_style TEXT,
                IN p_widget_id BIGINT,
                IN p_widget_type TEXT,
                IN p_user_id BIGINT DEFAULT NULL,
                IN p_user_name VARCHAR DEFAULT NULL,
                IN p_tenant_id BIGINT DEFAULT NULL,
                IN p_is_active BOOLEAN DEFAULT TRUE,
                IN p_current_time TIMESTAMPTZ DEFAULT now(),
                IN p_id BIGINT DEFAULT NULL
            ) RETURNS TABLE (
                status TEXT,
                message TEXT,
                widget_id BIGINT
            )
            LANGUAGE plpgsql
            AS \$\$
            DECLARE
                return_layout_id BIGINT;
                v_new_data JSONB;
                v_log_data JSONB;
                v_log_success BOOLEAN;
                v_error_message TEXT;
            BEGIN
                -- Case 1: Insert new
                IF p_id IS NULL OR p_id = 0 THEN
                    SELECT alw.id
                    INTO return_layout_id
                    FROM app_layout_widgets alw
                    WHERE alw.x = p_x 
                    AND alw.y = p_y 
                    AND alw.w = p_w 
                    AND alw.h = p_h 
                    AND alw.style = p_style
                    AND alw.user_id = p_user_id
                    AND alw.widget_id = p_widget_id
                    AND alw.widget_type = p_widget_type
                    AND alw.tenant_id = p_tenant_id
                    AND alw.deleted_at IS NULL;

                    IF NOT FOUND THEN
                        INSERT INTO app_layout_widgets (
                            x, y, w, h, style, widget_id, widget_type,
                            user_id, tenant_id, is_active,
                            created_at, updated_at
                        ) VALUES (
                            p_x, p_y, p_w, p_h, p_style, p_widget_id, p_widget_type,
                            p_user_id, p_tenant_id, p_is_active,
                            p_current_time, p_current_time
                        )
                        RETURNING id INTO return_layout_id;

                        -- Build new snapshot
                        v_new_data := jsonb_build_object(
                            'id', return_layout_id,
                            'x', p_x,
                            'y', p_y,
                            'w', p_w,
                            'h', p_h,
                            'style', p_style,
                            'widget_id', p_widget_id,
                            'widget_type', p_widget_type,
                            'user_id', p_user_id,
                            'tenant_id', p_tenant_id,
                            'is_active', p_is_active,
                            'created_at', p_current_time
                        );
                        v_log_data := jsonb_build_object('widget_id', return_layout_id, 'new_data', v_new_data);

                        -- Log
                        IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                PERFORM log_activity(
                                    'widget.created',
                                    'Widget created by ' || p_user_name,
                                    'app_layout_widget',
                                    return_layout_id,
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

                        RETURN QUERY SELECT 'SUCCESS', 'Widget added successfully', return_layout_id;
                    ELSE
                        UPDATE app_layout_widgets alw
                        SET updated_at = p_current_time
                        WHERE alw.id = return_layout_id;

                        RETURN QUERY SELECT 'SUCCESS', 'Widget already exists and was updated', return_layout_id;
                    END IF;

                -- Case 2: Update existing
                ELSE
                    UPDATE app_layout_widgets alw
                    SET x = p_x,
                        y = p_y,
                        w = p_w,
                        h = p_h,
                        style = p_style,
                        widget_id = p_widget_id,
                        widget_type = p_widget_type,
                        user_id = p_user_id,
                        tenant_id = p_tenant_id,
                        is_active = p_is_active,
                        updated_at = p_current_time
                    WHERE alw.id = p_id
                    RETURNING alw.id INTO return_layout_id;

                    IF FOUND THEN
                        -- Build new snapshot
                        v_new_data := jsonb_build_object(
                            'id', return_layout_id,
                            'x', p_x,
                            'y', p_y,
                            'w', p_w,
                            'h', p_h,
                            'style', p_style,
                            'widget_id', p_widget_id,
                            'widget_type', p_widget_type,
                            'user_id', p_user_id,
                            'tenant_id', p_tenant_id,
                            'is_active', p_is_active,
                            'updated_at', p_current_time
                        );
                        v_log_data := jsonb_build_object('widget_id', return_layout_id, 'new_data', v_new_data);

                        -- Log
                        IF p_user_id IS NOT NULL AND p_user_name IS NOT NULL THEN
                            BEGIN
                                PERFORM log_activity(
                                    'widget.updated',
                                    'Widget updated by ' || p_user_name,
                                    'app_layout_widget',
                                    return_layout_id,
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

                        RETURN QUERY SELECT 'SUCCESS', 'Widget updated successfully', return_layout_id;
                    ELSE
                        INSERT INTO app_layout_widgets (
                            x, y, w, h, style, widget_id, widget_type,
                            user_id, tenant_id, is_active,
                            created_at, updated_at
                        ) VALUES (
                            p_x, p_y, p_w, p_h, p_style, p_widget_id, p_widget_type,
                            p_user_id, p_tenant_id, p_is_active,
                            p_current_time, p_current_time
                        )
                        RETURNING id INTO return_layout_id;

                        RETURN QUERY SELECT 'SUCCESS', 'Widget added successfully', return_layout_id;
                    END IF;
                END IF;

            EXCEPTION
                WHEN OTHERS THEN
                    RETURN QUERY SELECT 'ERROR', SQLERRM, NULL::BIGINT;
            END;
            \$\$;
        SQL);
    }
};