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
        DO $$
        DECLARE
            r RECORD;
        BEGIN 
            FOR r IN
                SELECT oid::regprocedure::text AS func_signature
                FROM pg_proc
                WHERE proname = 'get_all_app_widgets'
            LOOP
                EXECUTE format('DROP FUNCTION %s CASCADE;', r.func_signature);
            END LOOP;
        END$$;

        CREATE OR REPLACE FUNCTION get_all_app_widgets()
        RETURNS TABLE (
            widgets JSONB
        )
        LANGUAGE plpgsql
        AS $$
        DECLARE
            result JSONB;
        BEGIN
            -- Build the widget data
            WITH widget_data AS (
                SELECT
                    wc.category_name,
                    jsonb_build_object(
                        'id', w.id,
                        'image_path', w.image_path,
                        'design_obj', w.design_obj,
                        'design_component', w.design_component,
                        'widget_type', w.widget_type,
                        'is_enable_for_web_app', w.is_enable_for_web_app,
                        'is_enable_for_mobile_app', w.is_enable_for_mobile_app
                    ) AS widget_info
                FROM
                    app_widgets w
                JOIN
                    app_widgets_categories wc
                ON
                    w.category_id = wc.id
            ),
            grouped_data AS (
                SELECT
                    category_name,
                    jsonb_agg(widget_info) AS category_related_all_object
                FROM
                    widget_data
                GROUP BY
                    category_name
            )
            SELECT
                jsonb_agg(
                    jsonb_build_object(
                        'category_name', category_name,
                        'category_related_all_object', category_related_all_object
                    )
                )
            INTO result
            FROM
                grouped_data;

            -- Return the result
            RETURN QUERY SELECT result;
        END;
        $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS get_all_app_widgets();');
    }
};
