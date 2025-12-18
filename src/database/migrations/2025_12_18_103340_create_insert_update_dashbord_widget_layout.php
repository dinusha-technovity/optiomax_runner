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

        DO $$
        DECLARE
            r RECORD;
        BEGIN
            -- Drop all existing versions of the function
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'insert_or_update_dashboard_app_layout_widget_bulk'
             LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        
      CREATE OR REPLACE FUNCTION insert_or_update_dashboard_app_layout_widget_bulk(
            p_widgets_json JSONB,
            p_tenant_id BIGINT,
            p_user_id BIGINT,
            p_user_name VARCHAR DEFAULT NULL,
            p_current_time TIMESTAMPTZ DEFAULT now()
        )
        RETURNS TABLE (
            status TEXT,
            message TEXT,
            widget_id BIGINT
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            r RECORD;
            return_layout_id BIGINT;
            existing_widget_id BIGINT;
            v_new_data JSONB;
            v_log_data JSONB;
            v_log_success BOOLEAN;
            v_error_message TEXT;
        BEGIN
            /*
            * Iterate over widgets JSON array
            */
            FOR r IN
                SELECT *
                FROM jsonb_to_recordset(p_widgets_json) AS w(
                    id BIGINT,
                    x DOUBLE PRECISION,
                    y DOUBLE PRECISION,
                    w DOUBLE PRECISION,
                    h DOUBLE PRECISION,
                    style TEXT,
                    widget_id BIGINT,
                    widget_type TEXT,
                    is_active BOOLEAN
                )
            LOOP
                /*
                * =========================
                * CASE 1: INSERT
                * =========================
                */
                IF r.id IS NULL OR r.id = 0 THEN

                    -- Check duplicate widget per tenant + user
                    SELECT alw.id
                    INTO existing_widget_id
                    FROM app_layout_widgets alw
                    WHERE alw.tenant_id = p_tenant_id
                    AND alw.user_id = p_user_id
                    AND alw.widget_id = r.widget_id
                    AND alw.deleted_at IS NULL
                    LIMIT 1;

                    IF existing_widget_id IS NOT NULL THEN
                        RETURN QUERY
                        SELECT
                            'ERROR',
                            'This widget already added to your dashboard',
                            existing_widget_id;
                        CONTINUE;
                    END IF;

                    -- Check duplicate with same position + style
                    SELECT alw.id
                    INTO return_layout_id
                    FROM app_layout_widgets alw
                    WHERE alw.x = r.x
                    AND alw.y = r.y
                    AND alw.w = r.w
                    AND alw.h = r.h
                    AND alw.style = r.style
                    AND alw.user_id = p_user_id
                    AND alw.widget_id = r.widget_id
                    AND alw.widget_type = r.widget_type
                    AND alw.tenant_id = p_tenant_id
                    AND alw.deleted_at IS NULL;

                    IF NOT FOUND THEN
                        INSERT INTO app_layout_widgets (
                            x, y, w, h, style,
                            widget_id, widget_type,
                            user_id, tenant_id,
                            is_active,
                            created_at, updated_at
                        )
                        VALUES (
                            r.x, r.y, r.w, r.h, r.style,
                            r.widget_id, r.widget_type,
                            p_user_id, p_tenant_id,
                            COALESCE(r.is_active, TRUE),
                            p_current_time, p_current_time
                        )
                        RETURNING id INTO return_layout_id;

                        -- Snapshot
                        v_new_data := jsonb_build_object(
                            'id', return_layout_id,
                            'x', r.x,
                            'y', r.y,
                            'w', r.w,
                            'h', r.h,
                            'style', r.style,
                            'widget_id', r.widget_id,
                            'widget_type', r.widget_type,
                            'user_id', p_user_id,
                            'tenant_id', p_tenant_id,
                            'is_active', COALESCE(r.is_active, TRUE),
                            'created_at', p_current_time
                        );

                        v_log_data := jsonb_build_object(
                            'widget_id', return_layout_id,
                            'new_data', v_new_data
                        );

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
                                v_error_message := SQLERRM;
                            END;
                        END IF;

                        RETURN QUERY
                        SELECT
                            'SUCCESS',
                            'Widget added successfully',
                            return_layout_id;

                    ELSE
                        UPDATE app_layout_widgets
                        SET updated_at = p_current_time
                        WHERE id = return_layout_id;

                        RETURN QUERY
                        SELECT
                            'SUCCESS',
                            'Widget already exists and was updated',
                            return_layout_id;
                    END IF;

                /*
                * =========================
                * CASE 2: UPDATE
                * =========================
                */
                ELSE
                    -- Prevent another widget with same tenant + user + widget_id
                    SELECT alw.id
                    INTO existing_widget_id
                    FROM app_layout_widgets alw
                    WHERE alw.tenant_id = p_tenant_id
                    AND alw.user_id = p_user_id
                    AND alw.widget_id = r.widget_id
                    AND alw.id <> r.id
                    AND alw.deleted_at IS NULL
                    LIMIT 1;

                    IF existing_widget_id IS NOT NULL THEN
                        RETURN QUERY
                        SELECT
                            'ERROR',
                            'Another widget already exists for this tenant, user, and widget combination',
                            existing_widget_id;
                        CONTINUE;
                    END IF;

                    UPDATE app_layout_widgets
                    SET
                        x = r.x,
                        y = r.y,
                        w = r.w,
                        h = r.h,
                        style = r.style,
                        widget_id = r.widget_id,
                        widget_type = r.widget_type,
                        user_id = p_user_id,
                        tenant_id = p_tenant_id,
                        is_active = COALESCE(r.is_active, TRUE),
                        updated_at = p_current_time
                    WHERE id = r.id
                    RETURNING id INTO return_layout_id;

                    IF FOUND THEN
                        v_new_data := jsonb_build_object(
                            'id', return_layout_id,
                            'x', r.x,
                            'y', r.y,
                            'w', r.w,
                            'h', r.h,
                            'style', r.style,
                            'widget_id', r.widget_id,
                            'widget_type', r.widget_type,
                            'user_id', p_user_id,
                            'tenant_id', p_tenant_id,
                            'is_active', COALESCE(r.is_active, TRUE),
                            'updated_at', p_current_time
                        );

                        v_log_data := jsonb_build_object(
                            'widget_id', return_layout_id,
                            'new_data', v_new_data
                        );

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
                            EXCEPTION WHEN OTHERS THEN
                                NULL;
                            END;
                        END IF;

                        RETURN QUERY
                        SELECT
                            'SUCCESS',
                            'Widget updated successfully',
                            return_layout_id;

                    ELSE
                        -- Fallback insert (exact parity)
                        INSERT INTO app_layout_widgets (
                            x, y, w, h, style,
                            widget_id, widget_type,
                            user_id, tenant_id,
                            is_active,
                            created_at, updated_at
                        )
                        VALUES (
                            r.x, r.y, r.w, r.h, r.style,
                            r.widget_id, r.widget_type,
                            p_user_id, p_tenant_id,
                            COALESCE(r.is_active, TRUE),
                            p_current_time, p_current_time
                        )
                        RETURNING id INTO return_layout_id;

                        RETURN QUERY
                        SELECT
                            'SUCCESS',
                            'Widget added successfully',
                            return_layout_id;
                    END IF;
                END IF;
            END LOOP;

        EXCEPTION
            WHEN OTHERS THEN
                RETURN QUERY
                SELECT
                    'ERROR',
                    SQLERRM,
                    NULL::BIGINT;
        END;
        $$;

      SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
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
                WHERE proname = 'insert_or_update_dashboard_app_layout_widget_bulk'
             LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;
        SQL);
    }
};
